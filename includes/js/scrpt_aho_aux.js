//#region FUNCIONES PARA FORMATOS DE LIBRETA

//FUNCION PARA CONVERTIR MES A LETRAS
function convertir_mes(numeroMes) {
    var meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
    var mes = "";
    if (!isNaN(numeroMes) && numeroMes >= 1 && numeroMes <= 12) {
        mes = meses[numeroMes - 1];
    }
    return mes;
}
// MAIN
function impresion_libreta_main(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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

function impresion_libreta_djd(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log("impresion libreta main")
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
            doc.text(64, pos, "");
            doc.text(88, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";

            doc.text(30, pos, transaccion);
            doc.text(64, pos, "" + pad(currency(monto)));
            doc.text(88, pos, "");
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

function impresion_libreta_otziles(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    var inif = parseInt(datos[0][2]); CRE
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
    var i = posini;
    var tiptr;
    var saldo = saldoo;
    var monto = parseFloat(0);

    while (i < datos[1].length) {
        doc.setFontSize(8);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);

        fecha = conviertefecha(datos[1][i]['dfecope']);
        doc.text(10, pos, "" + fecha);

        doc.text(32, pos, "" + datos[1][i]['numlinea']);

        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];

        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Ret/Deb";
            doc.text(41, pos, transaccion);
            doc.text(64, pos, "");
            doc.text(58, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Dep/Int";
            doc.text(41, pos, transaccion);
            doc.text(86, pos, "" + pad(currency(monto)));
            doc.text(88, pos, "");
        }

        doc.text(117, pos, "" + pad(currency(datos[1][i]['saldo'])));

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
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                doc.autoPrint();
                window.open(doc.output('bloburl'));
            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success');
            }
        });
    } else {
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }

    if (bandera == 1) {
        // Llamada recursiva SIN el parámetro de contador
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}

function impresion_libreta_coopedjd(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log("impresion libreta main")
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
        doc.text(x + 10, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";
            doc.text(x + 30, pos, transaccion);
            doc.text(x + 64, pos, "");
            doc.text(x + 88, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";

            doc.text(x + 30, pos, transaccion);
            doc.text(x + 64, pos, "" + pad(currency(monto)));
            doc.text(x + 88, pos, "");
        }
        doc.text(x + 113, pos, "" + pad(currency(datos[1][i]['saldo'])));
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
    // // console.log(datos);
    alert("Impresion de certificado");
    var opciones = {
        orientation: "p",
        unit: "mm",
        format: [240, 300],
    };
    var doc = new jsPDF(opciones);
    doc.setFont("courier", "bold");
    doc.setFontSize(11);

    var oficina = datos[0][17];
    var recibo = datos[0][18];
    var i = 5;
    var ini = 55;
    var margenizquierdo = 7;
    // doc.setFontStyle('bold');
    doc.text(margenizquierdo, ini, "CERTIFICADO No.  " + "DJD - Y" + datos[0][0]);
    // doc.text(margenizquierdo, ini, " "); //NO.
    // doc.text( 65, 34, 'C.I. ' + datos[0][16]); //C.I.
    ini = ini + i;
    doc.setFontSize(14);
    doc.text(margenizquierdo + 70, ini, "Q : " + datos[0][7]); // Monto
    doc.setFontSize(11);
    ini = ini + i;
    doc.text(margenizquierdo, ini, "ASOCIADO/A: ");
    doc.text(margenizquierdo + 55, ini, datos[0][1]); //Nombre
    ini = ini + i;
    doc.text(margenizquierdo, ini, "IDENTIFICACIÓN DPI: ");
    doc.text(margenizquierdo + 55, ini, datos[0][4]); //Dpi
    ini = ini + i;
    doc.text(margenizquierdo, ini, "DIRECCIÓN: ");
    doc.text(margenizquierdo + 55, ini, datos[0][3]); //Direccion
    ini = ini + i;
    doc.text(margenizquierdo, ini, "TELEFONO: ");
    doc.text(margenizquierdo + 55, ini, datos[0][5]); //Tel
    ini = ini + i;
    doc.text(margenizquierdo, ini, "CUENTA DE AHORRO A PLAZO FIJO No. " + datos[0][2]); //CUENTA
    // doc.text(margenizquierdo + 55, ini,datos[0][2]); //CUENTA
    ini = ini + i;

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
    doc.text(margenizquierdo, ini, "SUMA DE: ");
    ini = ini + i;
    doc.text(margenizquierdo, ini, datos[0][6] + '  ' + ptdecimal + '/100'); // La cantidad en letras
    ini = ini + i;

    var tasaFormateada = parseFloat(datos[0][11]).toFixed(2) + "%";
    doc.text(margenizquierdo, ini, "TASA DE INTERES: ");
    doc.text(margenizquierdo + 55, ini, tasaFormateada); // tasa
    ini = ini + i;

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
    doc.text(margenizquierdo, ini, "CAPITALIZACIÓN DE INTERESES: " + texto);
    // doc.text(60, 100.5, " " + texto); // Mensual o Vencimiento
    ini = ini + i;

    doc.text(margenizquierdo, ini, "FECHA DE INICIO: ");
    doc.text(margenizquierdo + 55, ini, datos[0][9]); //Fec.deposito
    ini = ini + i;
    doc.text(margenizquierdo, ini, "FECHA DE VENCIMIENTO:");
    doc.text(margenizquierdo + 55, ini, datos[0][10]); //Fec. vencimiento
    ini = ini + i;
    doc.text(margenizquierdo, ini, "PLAZO:");
    doc.text(margenizquierdo + 55, ini, datos[0][8] + "   " + "Días"); //plazos
    ini = ini + i;

    // Restablece el estilo de fuente a normal
    // doc.setFontStyle("normal");

    doc.text(margenizquierdo, ini, "INTERESES:");
    doc.text(margenizquierdo + 55, ini, parseFloat(datos[0][12]).toFixed(2)); // Interes calcu
    ini = ini + i;
    doc.text(margenizquierdo, ini, "(-) I.S.R.C.");
    doc.text(margenizquierdo + 55, ini, parseFloat(datos[0][13]).toFixed(2)); // ipf
    ini = ini + i;
    doc.text(margenizquierdo, ini, "TOTAL A PAGAR:");
    doc.text(margenizquierdo + 55, ini, parseFloat(datos[0][14]).toFixed(2)); //totalrecibir
    ini = ini + i;

    // BENEFICIARIOS
    // doc.text(margenizquierdo, ini, "BENEFICIARIO:");
    // ini = ini + i;
    // doc.text(margenizquierdo, ini, "BENEFICIARIO:");

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
        var baseXNombre = 7;
        var baseXParentesco = 100;
        var baseY = 140.5;
        var lineHeight = 4;

        nombres.forEach((nombre, index) => {
            var posY = baseY + (index * lineHeight);
            doc.text(baseXNombre, posY, "BENEFICIARIO: " + nombre + " " + parentescos[index]); // Nombre
            // doc.text(baseXParentesco, posY, parentescos[index]); // Parentesco
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
    // // console.log(datos);
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
        doc.setFontSize(11);
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

function impresion_certificado_djd(datos) {
    // // console.log(datos);
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
    // doc.text(7, 131, "(-) I.S.R.C.");
    // doc.text(50, 131, " " + parseFloat(datos[0][13]).toFixed(2)); // ipf
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

function impresion_recibo_dep_ret_djd(datos) {
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
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
        doc.text(18, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";
            doc.text(36, pos, transaccion);
            doc.text(98, pos, "" + pad(currency(monto)));
            doc.text(74, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";

            doc.text(36, pos, transaccion);
            doc.text(98, pos, "");
            doc.text(74, pos, "" + pad(currency(monto)));
        }
        doc.text(125, pos, "" + pad(currency(datos[1][i]['saldo'])));
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

//libreta de copefunete
function impresion_libreta_copefuente(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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
    posvert = ini + 3;

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
        doc.text(8, pos, "" + fecha);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";
            doc.text(28, pos, transaccion);
            doc.text(83, pos, "" + pad(currency(monto)));
            doc.text(58, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";

            doc.text(28, pos, transaccion);
            doc.text(83, pos, "");
            doc.text(58, pos, "" + pad(currency(monto)));
        }
        doc.text(108, pos, "" + pad(currency(datos[1][i]['saldo'])));


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
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file, posvert += 4);
        //  console.log('1 datos ' , datos , '2 nfont' , inid , posac , nfront , '3 ndors' , ndors , 'suma anteriores',nfront + ndors, 'saldo'  , saldo ,'nfont +1 ' , nfront +1 , 'file', file);

    }
}



function impresion_libreta_copibelen(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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
        doc.text(8, pos, "" + fecha);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";
            doc.text(28, pos, transaccion);
            doc.text(58, pos, "" + pad(currency(monto)));
            doc.text(83, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";

            doc.text(28, pos, transaccion);
            doc.text(58, pos, "");
            doc.text(83, pos, "" + pad(currency(monto)));
        }
        doc.text(108, pos, "" + pad(currency(datos[1][i]['saldo'])));


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
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file, posvert += 4);
        //  console.log('1 datos ' , datos , '2 nfont' , inid , posac , nfront , '3 ndors' , ndors , 'suma anteriores',nfront + ndors, 'saldo'  , saldo ,'nfont +1 ' , nfront +1 , 'file', file);

    }
}



// CIACREHO -> FORMAS
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

// COOPRODE -> FORMAS
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
    doc.setFontSize(10);

    //var i = 0;
    var i = posini;
    var tiptr;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var monto = parseFloat(0);
    var pos = 74;
    while (i < (datos[1].length)) {
        //console.log(datos[1][i]['numlinea']+' - '+resta+' - '+ini);
        num = parseInt(datos[1][i]['numlinea']);
        // pos = (4 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(16, pos, "" + fecha);
        doc.text(46, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            // transaccion = "Retiro/Debito";
            // doc.text(51, pos, transaccion);
            doc.text(70, pos, "");
            doc.text(70, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            // transaccion = "Deposito/Intereses";
            // doc.text(51, pos, transaccion);
            doc.text(97, pos, "" + pad(currency(monto)));
            doc.text(97, pos, "");
        }

        doc.text(126, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
        pos += 5;
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


function impresion_libreta_corpocredit(datos, resta, ini, posini, posfin, saldoo, numi, file) {

    // // console.log(datos);
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
        // console.log(datos[1][i]['id_agencia']);
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        fecha = conviertefecha(datos[1][i]['dfecope']);

        if (datos[1][i]['agencia_libreta'] != '4') {
            doc.text(10, pos, "" + fecha);
            // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
            monto = parseFloat(datos[1][i]['monto']);
            tiptr = datos[1][i]['ctipope'];
            if (tiptr == "R") {
                saldo = parseFloat(saldo) - parseFloat(monto);
                transaccion = "Retiro/Debito";
                //esto es numero de documento
                doc.text(33, pos, (datos[1][i]['cnumdoc']));
                doc.text(90, pos, "" + pad(currency(monto)));
                doc.text(66, pos, "");
            }
            if (tiptr == "D") {
                saldo = parseFloat(saldo) + parseFloat(monto);
                transaccion = "Deposito/Interes";
                //esto es numero de documento
                doc.text(33, pos, (datos[1][i]['cnumdoc']));
                doc.text(90, pos, "");
                doc.text(66, pos, "" + pad(currency(monto)));
            }
            doc.text(117, pos, "" + pad(currency(datos[1][i]['saldo'])));
            if (num >= posfin) {
                posac = i + 1;
                bandera = (num >= (nfront + ndors)) ? 0 : 1;
                break;
            }
        } else {
            doc.text(10, pos, "" + fecha);
            // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
            monto = parseFloat(datos[1][i]['monto']);
            tiptr = datos[1][i]['ctipope'];
            if (tiptr == "R") {
                saldo = parseFloat(saldo) - parseFloat(monto);
                transaccion = "Retiro/Debito";
                //esto es numero de documento
                doc.text(35, pos, (datos[1][i]['cnumdoc']));
                doc.text(66, pos, "");
                doc.text(85, pos, "" + pad(currency(monto)));
            }
            if (tiptr == "D") {
                saldo = parseFloat(saldo) + parseFloat(monto);
                transaccion = "Deposito/Intereses";
                //esto es numero de documento
                doc.text(35, pos, (datos[1][i]['cnumdoc']));
                doc.text(61, pos, "" + pad(currency(monto)));
                doc.text(85, pos, "");

            }
            doc.text(112, pos, "" + pad(currency(datos[1][i]['saldo'])));
            if (num >= posfin) {
                posac = i + 1;
                bandera = (num >= (nfront + ndors)) ? 0 : 1;
                break;
            }
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
        doc.text(15, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";
            doc.text(35, pos, transaccion);
            doc.text(69, pos, "");
            doc.text(93, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";
            doc.text(35, pos, transaccion);
            doc.text(69, pos, pad(currency(monto)));
            doc.text(93, pos, "");
        }
        doc.text(118, pos, "" + pad(currency(datos[1][i]['saldo'])));
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


function impresion_libreta_adif(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log("impresion libreta main")
    // console.log(datos)\
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
        doc.text(5, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";
            doc.text(32, pos, transaccion);
            doc.text(65, pos, "" + pad(currency(monto)));
            doc.text(70, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";

            doc.text(32, pos, transaccion);
            doc.text(50, pos, "");
            doc.text(90, pos, "" + pad(currency(monto)));
        }
        doc.text(122, pos, "" + pad(currency(datos[1][i]['saldo'])));
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


// listo
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
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(19, pos, "" + fecha);
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
            doc.text(40, pos, "" + cnumdoc);
            doc.text(62, pos, transaccion);
            doc.text(73, pos, "");
            doc.text(80, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            let cnumdoc = datos[1][i]['cnumdoc'];
            if (cnumdoc.length > 10) { // Truncar
                cnumdoc = cnumdoc.substring(0, 10) + "...";
            }
            doc.text(40, pos, "" + cnumdoc);
            transaccion = "DEPOSITO";
            doc.text(35, pos, "");
            doc.text(62, pos, transaccion);
            doc.text(105, pos, "" + pad(currency(monto)));
        }
        doc.text(130, pos, "" + pad(currency(datos[1][i]['saldo'])));
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

function impresion_libreta_coopeadg(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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
        doc.text(18, pos, "" + fecha);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        interes = datos[1][i]['crazon'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "RET";
            let cnumdoc = datos[1][i]['cnumdoc'];
            if (cnumdoc.length > 4) {
                // Truncar
                cnumdoc = cnumdoc.substring(0, 4) + "/";
            }
            doc.text(40, pos, "" + cnumdoc);
            if (tiptr == "R" & interes == "INTERES") {
                transaccion = "INT";
                doc.text(42, pos, transaccion);
                doc.text(100, pos, "" + pad(currency(monto)));
            } else {
                doc.text(48, pos, transaccion);
                doc.text(79, pos, "");
                doc.text(84, pos, "" + pad(currency(monto)));
            }
        }
        //Deposito
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            let cnumdoc = datos[1][i]['cnumdoc'];
            if (cnumdoc.length > 4) {
                // Truncar
                cnumdoc = cnumdoc.substring(0, 4) + "/";
            }
            doc.text(40, pos, "" + cnumdoc);
            if (tiptr == "D" & interes == "INTERES") {
                transaccion = "C.INT";
                doc.text(48, pos, transaccion);
                doc.text(104, pos, "" + pad(currency(monto)));
            } else {
                transaccion = "DEP";
                doc.text(48, pos, "");
                doc.text(48, pos, transaccion);
                doc.text(61, pos, "" + pad(currency(monto)));
            }
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


function impresion_libreta_multinorte(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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
            doc.text(75, pos, "" + pad(currency(monto)));
            doc.text(86, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";

            doc.text(30, pos, transaccion);
            doc.text(72, pos, "");
            doc.text(100, pos, "" + pad(currency(monto)));
        }
        doc.text(125, pos, "" + pad(currency(datos[1][i]['saldo'])));


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

function impresion_libreta_mayaland(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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
        doc.text(4, pos, "" + fecha);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Ret/Deb";
            doc.text(28, pos, transaccion);
            doc.text(50, pos, "" + pad(currency(monto)));
            doc.text(86, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Dep/Inte";

            doc.text(28, pos, transaccion);
            doc.text(72, pos, "");
            doc.text(70, pos, "" + pad(currency(monto)));
        }
        doc.text(90, pos, "" + pad(currency(datos[1][i]['saldo'])));


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

function impresion_libreta_cope27(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    //console.log(datos);
    //FRONT_INI = 71  -- NUMFRONT = 26  ---  NUMDORS - 35
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
    doc.setFontSize(6);
    doc.setFont("helvetica", "bold");
    var i = posini;
    var tiptr;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini) - 8;
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        idagencia = datos[1][i]['id_agencia'];
        if( idagencia == null ){
            idagencia = "1";
        }
        // console.log(fecha);
        doc.text(8, pos, fecha); // FECHA
        doc.text(28, pos,"" + idagencia); // AGENCIA
        doc.text(45, pos, "" + datos[1][i]['cnumdoc']); // NO DE DOCUMENTO
        detalle = datos[1][i]['crazon'];
        //doc.text(58, pos, "" + detalle);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** *///DEFINIR DE ES RETIRO O DEPOSITO
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            // transaccion = "Retiro";
            doc.text(85, pos, "" + pad(currency(monto)));
            doc.text(112, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            // transaccion = "Deposito";
            // doc.text(30, pos, transaccion);
            doc.text(85, pos, "");
            doc.text(60, pos, "" + pad(currency(monto)));
        }
        doc.text(120, pos, "" + pad(currency(datos[1][i]['saldo']))); // SALDO ACTUAL
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

//#endregion

function impresion_libreta_credivasquez(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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
            doc.text(88, pos, "" + pad(currency(monto)));
            doc.text(64, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";

            doc.text(30, pos, transaccion);
            doc.text(88, pos, "");
            doc.text(64, pos, "" + pad(currency(monto)));
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

//#region FUNCIONES PARA FORMATO DE RETIROS Y DEPOSITOS
// MAIN
function impresion_recibo_dep_ret_main(datos) {
    // console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 35;
    var margenizquierdo = 18;
    while (i < 2) {
        doc.setFontSize(10);
        doc.text(margenizquierdo, ini, 'Cuenta: ' + datos[2]);
        doc.text(margenizquierdo + 80, ini, 'Fecha: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo + 80, ini, 'Monto: Q ' + datos[3]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operación: ' + datos[6]);
        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Producto: ' + datos[16]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, datos[10]);

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
function impresion_recibo_dep_ret_credivasquez(datos) {
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
        doc.setFontSize(10);
        doc.text(margenizquierdo, ini, 'Cuenta: ' + datos[2]);
        doc.text(margenizquierdo + 80, ini, 'Fecha: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo + 80, ini, 'Monto: Q ' + datos[3]);
        if (datos[19] !== undefined && datos[19] !== null && String(datos[19]).trim() !== "" && String(datos[19]) !== "0") {
            ini = ini + 6;
            doc.text(margenizquierdo, ini, 'Boleta: ' + datos[19]);
        }
        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operación: ' + datos[6]);
        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Producto: ' + datos[16]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, datos[10]);

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_pruebas(datos) {
    // console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 35;
    var margenizquierdo = 18;
    while (i < 2) {
        doc.setFontSize(10);

        if (datos[23] == 'D') {

            doc.text(margenizquierdo, ini, 'Operación: Deposito a ' + datos[16]);

        } if (datos[23] == 'R') {

            doc.text(margenizquierdo, ini, 'Operación: Retiro a ' + datos[16]);

        }

        // ini = ini + 4;
        // doc.text(margenizquierdo + 18, ini,datos[16]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Cuenta: ' + datos[2]);
        doc.text(margenizquierdo + 80, ini, 'Fecha: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo + 80, ini, 'Monto: Q ' + datos[3]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Monto en letras: ' + datos[11]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, datos[10]);

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_kumool(datos) {
    // // console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 32;
    var margenizquierdo = 18;
    while (i < 2) {
        doc.setFontSize(12);

        if (datos[23] == 'D') {
            doc.text(margenizquierdo + 10, ini, "FECHA: " + datos[4]); // FECHA
            doc.text(margenizquierdo + 120, ini, "CANTIDAD:  Q." + datos[3]); // MONTO

            ini = ini + 8;
            doc.text(margenizquierdo + 20, ini, "CANTIDAD EN LETRAS: " + datos[11]); // MONTO EN LETRAS

            ini = ini + 8;
            doc.text(margenizquierdo + 20, ini, "CONCEPTO DE: ");

            ini = ini + 8;
            doc.text(margenizquierdo + 20, ini, datos[6] + " del cliente ");//operacion
            ini = ini + 8;
            doc.text(margenizquierdo + 20, ini, datos[7] + ","); // cliente

            ini = ini + 8;
            doc.text(margenizquierdo + 20, ini, datos[6] + " del cliente ");//operacion
            ini = ini + 8;
            doc.text(margenizquierdo + 20, ini, datos[7] + ","); // cliente

            ini = ini + 8;
            doc.text(margenizquierdo + 20, ini, 'con número de documento ' + datos[5] + " del producto " + datos[16]); // no de documento

            ini = ini + 10;
            doc.text(margenizquierdo + 10, ini, "CLIENTE: " + datos[7]); // cliente
            doc.text(margenizquierdo + 120, ini, "DPI: " + datos[15]); // dpi

        }
        if (datos[23] == 'R') {
            doc.text(margenizquierdo + 10, ini, datos[4]); // FECHA
            doc.text(margenizquierdo + 135, ini, datos[3]); // MONTO

            ini = ini + 21;
            doc.text(margenizquierdo + 38, ini, datos[11]); // MONTO EN LETRAS

            ini = ini + 12;
            doc.text(margenizquierdo + 20, ini, datos[6] + " del cliente ");//operacion
            ini = ini + 9;
            doc.text(margenizquierdo + 20, ini, datos[7] + ","); // cliente

            ini = ini + 8;
            doc.text(margenizquierdo + 20, ini, 'con número de documento ' + datos[5] + " del producto " + datos[16]); // no de documento

            ini = ini + 31;
            doc.text(margenizquierdo + 15, ini, datos[7]); // cliente
            doc.text(margenizquierdo + 85, ini, datos[15]); // dpi
        }

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_fefran(datos) {
    // // console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 32;
    var margenizquierdo = 18;
    while (i < 2) {
        doc.setFontSize(12);

        if (datos[23] == 'D') {
            doc.text(margenizquierdo + 10, ini, "FECHA: " + datos[4]); // FECHA
            doc.text(margenizquierdo + 120, ini, "CANTIDAD:  Q." + datos[3]); // MONTO

            ini = ini + 8;
            doc.text(margenizquierdo + 20, ini, "CANTIDAD EN LETRAS: " + datos[11]); // MONTO EN LETRAS

            ini = ini + 8;
            doc.text(margenizquierdo + 20, ini, "CONCEPTO DE: ");

            ini = ini + 8;
            doc.text(margenizquierdo + 20, ini, datos[6] + " del cliente ");//operacion
            ini = ini + 8;
            doc.text(margenizquierdo + 20, ini, datos[7] + ","); // cliente

            ini = ini + 8;
            doc.text(margenizquierdo + 20, ini, datos[6] + " del cliente ");//operacion
            ini = ini + 8;
            doc.text(margenizquierdo + 20, ini, datos[7] + ","); // cliente

            ini = ini + 8;
            doc.text(margenizquierdo + 20, ini, 'con número de documento ' + datos[5] + " del producto " + datos[16]); // no de documento

            ini = ini + 10;
            doc.text(margenizquierdo + 10, ini, "CLIENTE: " + datos[7]); // cliente
            doc.text(margenizquierdo + 120, ini, "DPI: " + datos[15]); // dpi

        }
        if (datos[23] == 'R') {
            doc.text(margenizquierdo + 10, ini, datos[4]); // FECHA
            doc.text(margenizquierdo + 135, ini, datos[3]); // MONTO

            ini = ini + 21;
            doc.text(margenizquierdo + 38, ini, datos[11]); // MONTO EN LETRAS

            ini = ini + 12;
            doc.text(margenizquierdo + 20, ini, datos[6] + " del cliente ");//operacion
            ini = ini + 9;
            doc.text(margenizquierdo + 20, ini, datos[7] + ","); // cliente

            ini = ini + 8;
            doc.text(margenizquierdo + 20, ini, 'con número de documento ' + datos[5] + " del producto " + datos[16]); // no de documento

            ini = ini + 31;
            doc.text(margenizquierdo + 15, ini, datos[7]); // cliente
            doc.text(margenizquierdo + 85, ini, datos[15]); // dpi
        }

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_libreta_coinco(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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
        doc.text(15, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";
            doc.text(35, pos, transaccion);
            doc.text(64, pos, "");
            doc.text(70, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";

            doc.text(35, pos, transaccion);
            doc.text(95, pos, "" + pad(currency(monto)));
            doc.text(88, pos, "");
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

    // Inicia el ciclo para el documento
    while (i < 2) {
        doc.setFontSize(11);

        // Verifica si datos[23] es 'D' o 'R' para ajustar el diseño
        if (datos[23] === 'D') {
            // Diseño para 'D' (Depósito)
            ini = ini + 2;
            /* Por: Q  */ doc.text(160, ini, datos[3]);

            ini = ini + 10;
            /* NIT  */

            ini = ini + 8;
            /* Codigo Asociado No. */ doc.text(40, ini, datos[21]);
            /* Recibi de: */ doc.text(100, ini, datos[7]);

            ini = ini + 8;
            /*La Cantidad de:*/ doc.text(50, ini, datos[11]);

            ini = ini + 7;
            // doc.text(margenizquierdo, ini, 'Por Concepto de: ');

            ini = ini + 7;
            // doc.text(24, ini, ' ' +'Cuota de ingreso');
            // doc.text(120, ini, ' ' +'Abono a capital');

            ini = ini + 7;
            // doc.text(24, ini, ' ' +'Aportaciones');
            // doc.text(120, ini, ' ' +'Aportaciones extraordinarias');

            ini = ini + 7;
            // doc.text(24, ini, ' ' +'Pago de interes sobre prestamos');
            // doc.text(120, ini, ' ' +'Cancelacion de prestamos');

            ini = ini + 3;
            /* Ahorros */ doc.text(7, ini, 'X');

            ini = ini + 14;
            if (datos[20] === 'E') {
                /* Efectivo: */ doc.text(40, ini, 'X');
            } else if (datos[20] === 'D') {
                /* Banco: */ doc.text(160, ini, 'X');
            }
            ini = ini + 9;
            doc.text(100, ini, datos[14]);
            doc.text(149, ini, datos[13]);
            let valor = datos[12].toString();
            let ultimosDosDigitos = valor.substring(valor.length - 2);
            doc.text(182, ini, ultimosDosDigitos);

        } else if (datos[23] === 'R') {
            // Diseño para 'R' (Retiro)
            ini = ini + 2;
            /* Por: Q  */ doc.text(156, ini, datos[3]);

            ini = ini + 10;
            /* NIT */

            ini = ini + 16;
            /* La Cantidad de: */ doc.text(60, ini, datos[11]);

            ini = ini + 28.6;
            doc.text(74, ini, 'X');
            doc.text(112, ini, 'Retiro de Ahorros');

            ini = ini + 11.5;
            if (datos[20] === 'E') {
                /* Efectivo: */ doc.text(36, ini, 'X');
            } else if (datos[20] === 'C') {
                /* Cheque: */ doc.text(100, ini, 'X');
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

function impresion_recibo_dep_ret_copeplus(datos) {
    // // console.log(datos);
    //     return;

    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };

    var oficina = datos[22];
    // var oficina = 2;

    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = (oficina == 2) ? 33 : 50;
    var fontSize = (oficina == 2) ? 10 : 12;
    var margenizquierdo = 30;
    while (i < 2) {
        doc.setFontSize(fontSize);
        doc.text(margenizquierdo, ini, 'Cuenta de ahorro No. ' + datos[2]);
        doc.text(110, ini, 'Fecha doc: ' + datos[4]);
        doc.text(180, ini, '' + datos[5]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Operacion y No. Docto: ' + datos[5]);
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

// PRIMAVERA -> FORMAS
function impresion_recibo_dep_ret_primavera(datos) {
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
    var ini = 40;
    var margenizquierdo = 10;
    while (i < 2) {
        doc.setFontSize(13);
        doc.setFontStyle('bold');
        doc.text(margenizquierdo, ini, 'Cuenta de ahorro No. ' + datos[2]);
        doc.text(120, ini, 'Fecha doc: ' + datos[4]);
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
// CIACREHO
function impresion_recibo_dep_ret_ciacreho(datos) {
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
    var ini = 40;
    var margenizquierdo = 10;
    while (i < 2) {
        doc.setFontSize(13);
        doc.setFontStyle('bold');
        doc.text(margenizquierdo, ini, 'Cuenta de ahorro No. ' + datos[2]);
        doc.text(120, ini, 'Fecha doc: ' + datos[4]);
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
        doc.text(margenizquierdo + 50, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);
        ini = ini + 5;
        doc.text(margenizquierdo + 50, ini, 'Fecha op: ' + datos[10]);

        ini = ini + 40;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

// COOPRODE -> FORMAS
function impresion_recibo_dep_ret_cooprode(datos) {
    // console.log(datos)
    // return;
    alert("Impresion de recibo");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);
    var i = 1;
    var ini = 27;

    if (datos[6][0] === "D") {
        ini = ini + 3;
        while (i < 2) {
            doc.text(76, ini - 5, datos[14]);//dia
            doc.text(90, ini - 5, datos[13]);//fechas
            doc.text(100, ini - 5, datos[12]);//fechas

            ini = ini + 8;
            doc.text(34, ini - 7, datos[7]);//name

            ini = ini + 25;
            doc.text(12, ini, datos[6]);
            doc.text(178, ini, datos[3]);

            ini = ini + 35;
            doc.text(178, ini, datos[3]);//total

            ini = ini + 6;
            doc.text(36, ini, datos[11]);

            ini = ini + 40;
            i++;
        }
        doc.autoPrint();
        window.open(doc.output('bloburl'))
    }

    if (datos[6][0] === "R") {
        while (i < 2) {
            // ini = ini + 1;
            doc.text(75, ini, datos[14]);//DIA
            doc.text(88, ini, datos[13]);//ME1
            doc.text(100, ini, datos[12]);//AÑO

            ini = ini + 35;
            doc.text(25, ini, datos[6]);//Concepto
            doc.text(158, ini, "Q. " + datos[3]);//Sub total
            doc.text(190, ini + 40, "Q. " + datos[3]);//Total

            doc.text(50, ini + 37, datos[11]);//letras
            doc.text(32, ini + 44, datos[7])
            doc.text(18, ini + 50, datos[15]);//dpi

            ini = ini + 40;
            i++;
        }
        doc.autoPrint();
        window.open(doc.output('bloburl'))
    }
}
// CREDIMARQ -> FORMAS
function impresion_recibo_dep_ret_credimarq(datos) {
    alert("Impresion de recibo");
    // console.log(datos);
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
        doc.setFontSize(9);
        doc.text(margenizquierdo + 130, ini - 35, 'Fecha operación: ' + datos[10]);

        ini = ini + 4;
        doc.setFontSize(12);
        doc.text(margenizquierdo, ini, 'Cuenta por Pagar No. ' + datos[2]);
        doc.text(110, ini, 'Fecha doc: ' + datos[4]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Operacion y No. Docto: ' + datos[5]);
        doc.text(150, ini, 'Monto: Q ' + datos[3]);

        // concepto con salto de linea
        var maxWidth = 180;
        var lineas = doc.splitTextToSize(datos[29], maxWidth);
        ini = ini + 7;
        lineas.forEach(function (linea, index) {
            doc.text(margenizquierdo, ini + (index * 5), linea);
        });

        //firmas
        ini = ini + 20;
        doc.text(margenizquierdo + 10, ini, 'F.___________________________');
        doc.setFontSize(9);
        doc.text(margenizquierdo + 20, ini + 5, datos[8] + ' ' + datos[9]);
        doc.setFontSize(12);
        doc.text(margenizquierdo + 100, ini, 'F.___________________________');
        doc.setFontSize(9);
        doc.text(margenizquierdo + 110, ini + 5, datos[7]);
        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}


// copefuente
function impresion_recibo_dep_ret_copefuente(datos) {
    alert("Impresion de recibo");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    //   // console.log(datos);
    //   return;

    var doc = new jsPDF(opciones);
    var fechaActual = new Date();
    // Obtener la fecha y hora
    var fecha = fechaActual.toLocaleDateString();
    var hora = fechaActual.toLocaleTimeString();
    var i = 1;
    var ini = 30;
    var margenizquierdo = 25;
    let descrip = datos[16];  //  valor original
    descrip = descrip.toString().toUpperCase();  // mayúsculas

    if (descrip.length > 20) {
        descrip = descrip.slice(0, 20) + '...';  // Truncar
    }


    let descrip2 = datos[16];  //  valor original
    descrip2 = descrip2.toString().toUpperCase();  // mayúsculas

    if (descrip2.length > 10) {
        descrip2 = descrip2.slice(0, 10) + '...';  // Truncar
    }

    while (i < 2) {
        doc.setFontSize(8);

        decimal = datos[3].toString();
        if (decimal.indexOf('.') != -1) {
            var decimal = decimal.split('.')[1];
        }

        var espacios = '                      ';

        doc.text(margenizquierdo + 20, ini + 5, 'NOMBRE DEL ASOCIADO      ' + datos[7] + '    ' + datos[4]);
        doc.text(margenizquierdo, ini + 10, 'ASOCIADO No. ' + datos[15] + "           " + datos[6]);
        doc.text(margenizquierdo + 60, ini + 15, ' DESCRIPCION');

        const datoview = datos[6].charAt(0) === 'D' ? datos[3] : '';
        const datoview2 = datos[6].charAt(0) === 'R' ? datos[3] : '';
        const datoview3 = datos[6].charAt(0) === 'D' ? ' ' : '-';
        const datoview4 = datos[6].charAt(0) === 'R' ? ' ' : '-';

        //primwera columna
        doc.text(margenizquierdo, ini + 20, 'INSCRIPCION' + espacios + '      ');
        doc.text(margenizquierdo, ini + 25, 'APORTACION' + espacios + '      ');
        doc.text(margenizquierdo, ini + 30, descrip + '            ' + espacios + datoview);
        doc.text(margenizquierdo, ini + 35, 'RETIRO DE ' + descrip2 + espacios + datoview2);
        doc.text(margenizquierdo, ini + 40, 'INGRESOS VARIOS' + '                   ');
        doc.text(margenizquierdo, ini + 45, 'TOTAL EN LETRAS: ' + datos[11]);
        // concepto con salto de linea
        var maxWidth = 170;
        var lineas = doc.splitTextToSize('CONCEPTO: ' + datos[29], maxWidth);
        lineas.forEach(function (linea, index) {
            doc.text(margenizquierdo, ini + 50 + (index * 5), linea);
        });

        //SEGUNDA COLUMNA  Q and -
        doc.text(margenizquierdo + 8, ini + 20, espacios + espacios + 'Q' + espacios + '-' + espacios + espacios + espacios + 'Q' + espacios + '-');
        doc.text(margenizquierdo + 8, ini + 25, espacios + espacios + 'Q' + espacios + '-' + espacios + espacios + espacios + 'Q' + espacios + '-');
        doc.text(margenizquierdo + 8, ini + 30, espacios + espacios + 'Q' + espacios + datoview3 + espacios + espacios + espacios + 'Q' + espacios + '-');
        doc.text(margenizquierdo + 8, ini + 35, espacios + espacios + 'Q' + espacios + datoview4 + espacios + espacios + espacios + 'Q' + espacios + '-');
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


function impresion_recibo_dep_ret_corpocredit(datos) {
    alert("Impresion de recibo");

    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    //   // console.log(datos);
    //   return;

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

        doc.text(margenizquierdo, ini - 6, 'Fecha ' + datos[4] + '  ' + hora);
        doc.text(margenizquierdo, ini, 'CORPOCREDIT R.L. /PRODUCTO: ' + datos[16]);
        doc.text(margenizquierdo, ini + 6, 'No. de Cuenta: ' + datos[2]);
        doc.text(margenizquierdo, ini + 12, 'No. de Boleta de Transaccion: ' + datos[5]);

        doc.text(margenizquierdo + 130, ini + 15, 'Efectivo: Q. ' + datos[3]);
        doc.text(margenizquierdo, ini + 19, 'Letras: ' + datos[11]);

        doc.text(margenizquierdo, ini + 28, 'C.I. ' + datos[18]);

        doc.text(margenizquierdo + 33, ini + 41, 'Asociado / A:  ' + datos[7]);
        doc.text(margenizquierdo + 33, ini + 47, 'Operación: ' + (datos[6] === "D" ? "DEPOSITO A CUENTA AHORRO" : (datos[6] === "R") ? "RETIRO A CUENTA AHORRO" : datos[6]));
        doc.text(margenizquierdo + 50, ini + 53, 'Usuario: ' + datos[17]);

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
    //   // console.log(datos);
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
        doc.text(margenizquierdo + 33, ini + 35, 'Operación: ' + (datos[6] === "D" ? "DEPOSITO A CUENTA AHORRO" : (datos[6] === "R") ? "RETIRO A CUENTA AHORRO" : datos[6]));
        doc.text(margenizquierdo + 50, ini + 40, 'Usuario: ' + datos[17]);

        ini = ini + 40;
        i++;

    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
//#endregion

//funcion copibelen
function impresion_recibo_dep_ret_copibelen(datos) {
    alert("Impresion de recibo");

    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    //   // console.log(datos);
    //   return;

    var doc = new jsPDF(opciones);
    var fechaActual = new Date();
    // Obtener la fecha y hora
    var fecha = fechaActual.toLocaleDateString();
    var hora = fechaActual.toLocaleTimeString();


    var i = 1;
    var ini = 18;
    var margenizquierdo = 20;
    while (i < 2) {
        doc.setFontSize(12);

        decimal = datos[3].toString();
        if (decimal.indexOf('.') != -1) {
            var decimal = decimal.split('.')[1];
        }

        // doc.text(margenizquierdo+90, ini -6, 'Fecha: ' + fecha + '   ' +'Hora: ' + hora );
        // doc.text(margenizquierdo, ini, 'COPIBELEN' + datos[16]);
        ini += 6;
        doc.text(margenizquierdo + 32, ini, 'No. de Cuenta: ' + datos[2] + "    Fecha: " + datos[4] + ' ' + hora);
        doc.text(margenizquierdo + 32, ini + 6, 'No. de Boleta de Transaccion: ' + datos[5] + "           Efectivo: Q. " + datos[3]);
        doc.text(margenizquierdo + 32, ini + 12, 'Letras: ' + datos[11] + ' CON ' + decimal + '/100');
        doc.text(margenizquierdo + 15, ini + 24, 'Asociado / A: ' + datos[7]);
        doc.text(margenizquierdo + 15, ini + 30, 'Operación: ' + (datos[6] === "D" ? "DEPOSITO A CUENTA AHORRO" : (datos[6] === "R") ? "RETIRO A CUENTA AHORRO" : datos[6]));

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
    //   // console.log(datos);
    //   return;

    var doc = new jsPDF(opciones);
    var fechaActual = new Date();
    // Obtener la fecha y hora
    var fecha = fechaActual.toLocaleDateString();
    var hora = fechaActual.toLocaleTimeString();

    var i = 1;
    var ini = 30;
    var margenizquierdo = 15;
    while (i < 2) {
        doc.setFontSize(10);

        decimal = datos[3].toString();
        if (decimal.indexOf('.') != -1) {
            var decimal = decimal.split('.')[1];
        }
        doc.text(margenizquierdo + 150, ini, datos[3]);//efectivo
        doc.text(margenizquierdo + 15, ini - 5, 'ADG  ' + fecha);//no de boleta
        doc.text(margenizquierdo + 8, ini + 3, 'ASOCIACION DE DESARROLLO GUATEMALTECO "ADG" '); //recibo de
        doc.text(margenizquierdo + 8, ini + 8, ' 2a. Calle 01-0310 Zona 4 Tecpan Guatemala, Chimaltenango'); //DIRECCION
        doc.text(margenizquierdo + 15, ini + 15, datos[11] + ' CON ' + decimal + '/100');//en letras
        //ini += 6;
        doc.text(margenizquierdo, ini + 30, 'No. de Boleta de Transaccion: ' + datos[5]);

        doc.text(margenizquierdo, ini + 35, 'Asociado / A:      ' + datos[7]);
        doc.text(margenizquierdo, ini + 40, 'Operación: ' + (datos[6] === "D" ? "DEPOSITO A CUENTA AHORRO" : (datos[6] === "R") ? "RETIRO A CUENTA AHORRO" : datos[6]));
        doc.text(margenizquierdo, ini + 50, 'Operador: ' + datos[8] + ' ' + datos[9]);
        doc.text(margenizquierdo + 168, ini + 97, datos[3]);

        ini = ini + 40;
        i++;

    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_coditoto(datos) {
    // console.log(datos)
    // return;
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
        doc.text(margenizquierdo + 100, ini - 32, ' ');
        doc.setFontSize(13);
        doc.text(margenizquierdo + 4, ini - 15, ' ' + datos[16]);
        doc.text(margenizquierdo, ini - 10, 'Número de cuenta de ahorro ' + datos[2]);
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
function impresion_recibo_dep_ret_multinorte(datos) {
    // console.log(datos)
    // return;
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
    var ini = 44;
    var margenizquierdo = 55;
    while (i <= 3) {
        doc.setFontSize(11);
        doc.setFontStyle('bold');
        doc.text(margenizquierdo + 85, ini - 28, ' ' + datos[5]);
        doc.setFontSize(13);
        doc.text(margenizquierdo + 4, ini - 15, ' ' + datos[16]);
        doc.text(margenizquierdo, ini - 10, 'Número de cuenta de ahorro ' + datos[2]);
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
    var ini = 55;
    var margenizquierdo = 80;
    while (i <= 1) {
        doc.setFontSize(11);
        doc.setFontStyle('bold');
        doc.text(margenizquierdo + 85, ini - 28, ' ' + datos[5]);
        doc.setFontSize(13);
        doc.text(margenizquierdo, ini - 10, 'Número de cuenta de ahorro ' + datos[2]);
        doc.text(margenizquierdo, ini - 5, 'Fecha del documento: ' + datos[4]);
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        doc.text(margenizquierdo, ini + 5, 'Operación y No. Docto: ' + datos[5]);
        doc.text(margenizquierdo, ini + 10, 'Monto: Q ' + datos[3]);
        doc.text(margenizquierdo, ini + 15, 'Operación: ' + datos[6]);
        doc.setFontSize(11);
        doc.text(margenizquierdo, ini + 20, 'Operador: ' + datos[8] + ' ' + datos[9]);
        doc.text(margenizquierdo, ini + 25, 'Fecha de la operación: ' + datos[10]);
        doc.text(margenizquierdo, ini + 35, '________________________       ________________________ ');
        doc.text(margenizquierdo, ini + 39, '          Cajero                        Cliente           ');
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
        doc.text(margenizquierdo + 90, ini - 28, ' ');
        doc.setFontSize(13);
        // doc.text(margenizquierdo, ini - 10, 'Número de cuenta de ahorro ' + datos[2]);
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

//coopeadg
function impresion_recibo_dep_ret_coopeadg(datos) {
    // console.log(datos);
    // return;
    var primeraletra = datos[6][0];
    var recibos = primeraletra;
    var montol = datos[11];
     var agencia = '';
    if(datos[22]==1){
        agencia = "Tecpán Guatemala, Chimaltenango. ";

    } if(datos[22]==2){
        agencia = " San José Poaquil ";

    } if(datos[22]==3){
        agencia = "Chupol ";
    }
    if (recibos == 'D') {

        alert("Impresion de recibo  ");
        var opciones = {
            orientation: 'p',
            unit: 'mm',
            format: [216, 300]
        };
        var doc = new jsPDF(opciones);
        var i = 1;
        var ini = 65;
        var margenizquierdo = 20;
        let fecha = datos[4];

        while (i < 2) {
            doc.setFontSize(12);
            // doc.text(margenizquierdo + 170, ini - 10, datos[5]);
            doc.text(margenizquierdo + 50, ini - 12, agencia + datos[4]);
            doc.text(margenizquierdo, ini, datos[6]);
            doc.text(margenizquierdo, ini + 5, 'Cliente: ' + datos[7] + ' Cod No. ' + datos[18]);
            doc.text(margenizquierdo, ini + 10, datos[16]);
            doc.text(margenizquierdo, ini + 15, 'Fecha del documento: ' + fecha);
            doc.text(margenizquierdo, ini + 20, 'No. Docto: ' + datos[5]);
            doc.text(margenizquierdo, ini + 25, 'Monto: Q ' + datos[3]);
            doc.text(margenizquierdo, ini + 30, 'Monto en letras: ' + datos[11]);
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
        var margenizquierdo = 15;

        doc.setFontSize(12);
        ini = ini + 20;
        doc.text(margenizquierdo, ini, agencia + datos[4]);
        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Pagado a:  ' + datos[7] + ' Cod No. ' + datos[18]);
        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Por Concepto: ' + datos[6]);
        ini = ini + 6;
        doc.text(margenizquierdo, ini, datos[16]);
        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Q: ' + datos[3]);
        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'La suma de: ' + datos[11]);
        ini = ini + 6;
        // Información adicional si es banco
        if (datos[20] == "C") {
            doc.text(margenizquierdo, ini, 'Cheque NO.: ' + datos[19]);
        }
        doc.setFontSize(10);
        // Validación de efectivo
        var validae = (datos[20] == "E") ? "X" : " ";
        doc.rect(margenizquierdo + 164, ini - 4, 5, 5); // Dibuja un cuadro para la "X"
        doc.text(margenizquierdo + 165, ini, validae); // Coloca la "X" dentro del cuadro
        doc.text(margenizquierdo + 144, ini, 'EFECTIVO:');
        ini = ini + 6;
        // Validación de banco
        var validac = (datos[20] == "C") ? "X" : " ";
        doc.rect(margenizquierdo + 164, ini - 4, 5, 5); // Dibuja un cuadro para la "X"
        doc.text(margenizquierdo + 165, ini, validac); // Coloca la "X" dentro del cuadro
        doc.text(margenizquierdo + 149, ini, 'BANCO:');
        ini = ini + 6;

        // doc.setFontSize(12);
        // ini = ini + 13;
        // doc.text(margenizquierdo + 10, ini, '________________________');
        // doc.text(margenizquierdo + 100, ini, '________________________');
        // ini = ini + 6;
        // doc.text(margenizquierdo + 35, ini, 'FIRMA');
        // doc.text(margenizquierdo + 114, ini, 'FIRMA Y SELLO');
        // ini = ini + 6;
        // doc.setFontSize(10);
        // doc.text(margenizquierdo + 33, ini - 2, '(CLIENTE)');
        // doc.text(margenizquierdo + 117, ini - 2, '(OPERADOR) ');



    } else {
    }


    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
//#end
function impresion_recibo_dep_ret_altascumbres(datos) {
    alert("Impresion de recibo");

    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    //   // console.log(datos);
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
        doc.text(margenizquierdo, ini - 10, 'Número de cuenta de ahorro ' + datos[2]);
        doc.text(margenizquierdo, ini - 5, 'Fecha del documento: ' + datos[4]);
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        doc.text(margenizquierdo, ini + 5, 'Operación y No. Docto: ' + datos[5]);
        doc.text(margenizquierdo, ini + 10, 'Monto: Q ' + datos[3]);
        doc.text(margenizquierdo, ini + 15, 'Operación: ' + datos[6]);
        doc.setFontSize(11);
        doc.text(margenizquierdo, ini + 20, 'Operador: ' + datos[8] + ' ' + datos[9]);
        doc.text(margenizquierdo, ini + 25, 'Fecha de la operación: ' + datos[10]);
        // doc.text(margenizquierdo, ini + 35, '________________________       ________________________ ');
        // doc.text(margenizquierdo, ini + 39, '          Cajero                        Cliente           ' );
        ini += 53;
        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_cope27(datos) {
    //alert("Impresion de recibo");
    // // console.log(datos);
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
    let margenizquierdo = 70;
    while (i <= 1) {
        doc.setFontSize(11);
        if (datos[23] == 'D') {

            doc.text(margenizquierdo, ini, 'Operación: Deposito a ' + datos[16]);

        } if (datos[23] == 'R') {

            doc.text(margenizquierdo, ini, 'Operación: Retiro a ' + datos[16]);

        }
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'No. Cuenta:  ' + datos[2]);
        doc.text(margenizquierdo + 65, ini, 'No. Docto: ' + datos[5]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Fecha del documento: ' + datos[4]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Monto: Q ' + datos[3]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Monto en letras: ' + datos[11]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Fecha de Impresion: ' + datos[10]);
        // Procesar los nombres es decir iniciales
        let inicialesNombre = datos[8].split(' ').map(palabra => palabra.charAt(0).toUpperCase()).join('.');
        let inicialesApellido = datos[9].split(' ').map(palabra => palabra.charAt(0).toUpperCase()).join('.');
        let textoOperador = `Operador:  ${inicialesNombre}.${inicialesApellido}.`;
        ini = ini + 5;
        doc.text(margenizquierdo, ini, textoOperador);


        ini += 53;
        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_otziles(datos) {
    // console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFont("courier", "bold");
    var i = 1;
    var ini = 30;
    var margenizquierdo = 55;
    while (i < 2) {
        doc.setFontSize(11);

        doc.text(margenizquierdo, ini, 'OTZ´ILES R.L. ' + datos[16]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Docto: ' + datos[5]);
        doc.text(margenizquierdo + 50, ini, 'Fecha doc: ' + datos[4]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'D.P.I: ' + datos[15]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Cuenta de ahorro No. ' + datos[2]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Monto: Q ' + datos[3]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, datos[11]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Operación: ' + datos[6]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);
        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Fecha operación: ' + datos[10]);

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}


function impresion_recibo_dep_ret_copeixil(datos) {
    // // console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 35;
    var margenizquierdo = 18;
    while (i < 2) {
        doc.setFontSize(10);

        doc.text(margenizquierdo, ini, 'COPEIXIL S.A / PRODUCTO: ' + datos[16]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Cuenta: ' + datos[2]);
        doc.text(margenizquierdo + 80, ini, 'Fecha: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo + 80, ini, 'Monto: Q ' + datos[3]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operación: ' + datos[6]);

        if (datos[23] == 'D') {
            let saldoActual1 = parseFloat(datos[24]);
            let deposito = parseFloat(datos[25]);
            let saldoAnterior1 = parseFloat((saldoActual1 - deposito).toFixed(2)); // Redondeo a 2 decimales
            ini = ini + 6;
            doc.text(margenizquierdo, ini, 'Saldo Anterior: Q. ' + saldoAnterior1.toFixed(2));
            doc.text(margenizquierdo + 70, ini, 'Saldo Actual: Q. ' + parseFloat(datos[24]).toFixed(2));
        }

        if (datos[23] == 'R') {
            let saldoActual = parseFloat(datos[24]);
            let retiro = parseFloat(datos[25]);
            let saldoAnterior = parseFloat((saldoActual + retiro).toFixed(2)); // Redondeo a 2 decimales
            ini = ini + 6;
            doc.text(margenizquierdo, ini, 'Saldo Anterior: Q. ' + saldoAnterior.toFixed(2));
            doc.text(margenizquierdo + 70, ini, 'Saldo Actual: Q. ' + parseFloat(datos[24]).toFixed(2));
        }

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, datos[10]);

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

//#region FORMATO PARA CERTIFICADO INICIAL
//MAIN
function impresion_certificado_main(datos) {
    // // console.log(datos);
    // return;
    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(13);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);

    const y = 45;
    const x = -10;
    //texto en negrita
    doc.setFont('helvetica', 'bold');
    doc.text(x + 28, y + 10, 'Nombre del Asociado:');
    doc.text(x + 28, y + 20, 'Cuenta No.');
    doc.text(x + 19, y + 35, "DPI:");
    doc.text(x + 95, y + 35, 'Teléfono:');

    doc.text(x + 19, y + 45, 'Dirección:');
    doc.text(x + 20, y + 60, 'Monto:');
    doc.text(x + 20, y + 70, 'Monto en letras:');

    doc.text(x + 20, y + 85, 'Plazo:');
    doc.text(x + 95, y + 85, 'Interes:');
    doc.text(x + 20, y + 95, 'Fecha de Apertura:');
    doc.text(x + 95, y + 95, 'Fecha de Vencimiento:');

    //texto normal
    doc.setFont('helvetica', 'normal');
    doc.text(x + 85, y + 10, datos[0][1]); //nombre del cliente
    doc.text(x + 85, y + 20, datos[0][2]); //cuenta
    doc.text(x + 29, y + 35, datos[0][4]); //Dpi
    doc.text(x + 120, y + 35, datos[0][5]); //telefono

    doc.text(x + 50, y + 45, datos[0][3]); //Direccion
    doc.text(x + 35, y + 60, 'Q.' + intmonto);//monto +15 de texto
    doc.text(x + 60, y + 70, datos[0][6]);// monto en letras

    doc.text(x + 35, y + 85, datos[0][8] + ' días.'); //PLAZO
    doc.text(x + 115, y + 85, intere + '%');//interes
    doc.text(x + 65, y + 95, fechaini);//FECHA INICIO
    doc.text(x + 148, y + 95, fechafin);//FECHA vence

    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    const max_caracteres = 10;
    // console.log(datos[1]);
    for (let i = 1; i < datos[1].length; i++) {
        beneficiarios = beneficiarios + datos[1][i]['nombre'];
        dpi = dpi + datos[1][i]['dpi'];
        decripc = decripc + datos[1][i]['codparent'];
        tel = tel + datos[1][i]['telefono'];
        if (i !== ((datos[1].length) - 1)) {
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

    // console.log(datos[1].length)

    if (datos[1].length > 0) {
        doc.setFont('helvetica', 'bold');
        doc.text(x + 55, y + 120, 'DATOS DE BENEFICIARIOS');
        doc.setFont('helvetica', 'normal');

        doc.setFont('helvetica', 'bold');
        doc.text(x + 20, y + 130, 'Nombre Completo');
        doc.text(x + 95, y + 130, 'Parentesco');
        doc.text(x + 130, y + 130, 'DPI');
        // doc.text(175,y+ 40, 'NO. TELEFONO');
        doc.setFont('helvetica', 'normal');

        doc.text(x + 20, y + 140, splitTitle); //beneficiario
        doc.text(x + 95, y + 140, decripc2); //parentezco
        doc.text(x + 120, y + 140, dpi2); //DPI beneficiario
        // doc.text(175,y+ 44, tel2); //telefono
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_certificado_micasa(datos) {
    //// console.log(datos);
    // return;
    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(10);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);
    var fecha = new Date(datos[0][15]);
    var dia = fecha.getDate() + 1;
    var mes = fecha.getMonth() + 1;
    var ano = fecha.getFullYear();
    // console.log(dia, mes, ano);

    const y = 45;
    const x = -10;

    //primera linea
    doc.setFont('helvetica', 'normal');
    doc.text(x + 53, y + 55, datos[0][1]); //nombre del cliente
    doc.text(x + 150, y + 55, datos[0][2]); //cuenta

    //segunda linea
    doc.text(x + 55, y + 61, datos[0][3]); //Direccion
    doc.text(x + 125, y + 61, datos[0][4]); //Dpi
    doc.text(x + 175, y + 61, datos[0][5]); //telefono
    console.log(datos[0][5]);
    //tercera linea
    doc.text(x + 170, y + 67, ' ' + intmonto);//monto numerico
    doc.text(x + 70, y + 67, datos[0][6]);// monto en letras

    //cuarta linea
    doc.text(x + 58, y + 72, datos[0][8] + ' días.'); //PLAZO
    doc.text(x + 165, y + 72, intere + '');//interes

    //quinta linea
    doc.text(x + 73, y + 78, fechaini);//FECHA INICIO
    doc.text(x + 170, y + 78, fechafin);//FECHA vence
    console.log(fechaini, fechafin);
    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    const max_caracteres = 10;
    // console.log(datos[1]);
    for (let i = 1; i < datos[1].length; i++) {
        beneficiario = datos[1][i]['nombre'];
        dpi = datos[1][i]['dpi'];
        decripc = datos[1][i]['codparent'];
        tel = datos[1][i]['telefono'];
        porcentaje = datos[1][i]['porcentaje'];
        // if (i !== ((datos[1].length) - 1)) {
        //     beneficiarios = beneficiarios + "\n";
        //     dpi = dpi + "\n";
        //     decripc = decripc + "\n";
        //     tel = tel + "\n";
        //     porcentaje = porcentaje + "\n";
        // }
        doc.setFont('helvetica', 'normal');

        doc.text(x + 40, y + 93 + i * 5, beneficiario); //beneficiario
        doc.text(x + 120, y + 93 + i * 5, decripc); //parentezco
        // doc.text(x + 120, y + 140, dpi2); //DPI beneficiario
        doc.text(x + 180, y + 93 + i * 5, porcentaje); //porsentaje beneficiario

    }
    // console.log(beneficiarios);
    // var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    // var dpi2 = doc.splitTextToSize(dpi, 180);
    // var decripc2 = doc.splitTextToSize(decripc, 180);
    // var tel2 = doc.splitTextToSize(tel, 180);
    // var porcentaje = doc.splitTextToSize(porcentaje, 180);

    // console.log(datos[1].length)

    // if (datos[1].length > 0) {
    //     //doc.setFont('helvetica', 'bold');
    //     //doc.text(x + 55, y + 120, 'DATOS DE BENEFICIARIOS');
    //     //doc.setFont('helvetica', 'normal');
    //     //doc.setFont('helvetica', 'bold');
    //     //doc.text(x + 20, y + 130, 'Nombre Completo');
    //     //doc.text(x + 95, y + 130, 'Parentesco');
    //     //doc.text(x + 130, y + 130, 'porsentaje');
    //     // doc.text(175,y+ 40, 'NO. TELEFONO');
    //     doc.setFont('helvetica', 'normal');

    //     doc.text(x + 40, y + 98, splitTitle); //beneficiario
    //     doc.text(x + 120, y + 98, decripc2); //parentezco
    //     // doc.text(x + 120, y + 140, dpi2); //DPI beneficiario
    //     doc.text(x + 180, y + 98, porcentaje); //porsentaje beneficiario
    //     // doc.text(175,y+ 44, tel2); //telefono
    // }

    doc.text(x + 90, y + 183, ' ' + dia);//DIA
    doc.text(x + 110, y + 183, ' ' + convertir_mes(mes));//MES
    doc.text(x + 135, y + 183, ' ' + ano);//AÑO

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_libreta_micasa(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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
        pos = (5 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(17, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";
            doc.text(36, pos, transaccion);
            doc.text(70, pos, "" + pad(currency(monto)));
            doc.text(88, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";

            doc.text(36, pos, transaccion);
            doc.text(64, pos, "");
            doc.text(94, pos, "" + pad(currency(monto)));
        }
        doc.text(115, pos, "" + pad(currency(datos[1][i]['saldo'])));
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

function impresion_certificado_credivasquez(datos) {
    // console.log(datos);
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    datosAsoc = datos[4];
    doc.setFontSize(12);

    // Convierte el texto a primeras letras en mayusculaS
    function Mayusini(texto) {
        return texto
            .toLowerCase()
            .replace(/\b\w/g, (letra) => letra.toUpperCase());
    }

    let montoEnLetras = Mayusini(datos[0][6]);
    let nomcli = Mayusini(datos[0][1]);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);
    var oficina = datos[0][17];

    const y = 40;
    const x = -10;

    //texto normal
    doc.setFont("Arial", "bold");
    doc.text(x + 75, y, datos[3]); //CODIGO DEL CLIENTE
    doc.text(x + 60, y + 8, nomcli); //nombre del cliente
    doc.text(x + 175, y + 8, datos[0][4]); //Dpi

    doc.text(x + 60, y + 18, datos[0][3]); //Direccion
    doc.text(x + 75, y + 28, 'Q.' + intmonto);//monto +15 de texto
    doc.text(x + 105, y + 28, montoEnLetras);// monto en letras
    doc.text(x + 60, y + 38, datos[0][8] + ' días.'); //PLAZO
    doc.text(x + 90, y + 48.5, fechafin);//FECHA vence
    doc.text(x + 70, y + 58.5, intere + '% Anual');//interes

    var lugaroficina = (oficina == "001") ? "Crucero del Canton Pasajoc, Totonicapan" : "Crucero del Canton Pasajoc, Totonicapan";
    doc.text(x + 50, y + 202, lugaroficina + "  " + datos[0][15]);

    //    doc.text( x+120,y+35, datos[0][5]); //telefono
    //    doc.text( x+85, y+20, datos[0][2]); //cuenta
    //    doc.text( x+65, y+95, fechaini);//FECHA INICIO

    var i = 1;
    var ini = 118;
    while (i < datos[1].length) {
        doc.text(50, ini, datos[1][i]['nombre']);
        doc.text(175, ini, datos[1][i]['codparent']);
        doc.text(50, ini + 10, datos[1][i]['dpi']);

        ini = ini + 25;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_certificado_copeixil(datos) {
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    //   console.log(datos[4].nombre);
    //   console.table(datos[4])
    //   console.log(datos[4])
    // return;
    var doc = new jsPDF(opciones);

    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = 210;
    var margenSuperior = 10;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);

    doc.setFontSize(11);

    // Calcular posición centrada
    function textoCentrado(texto, y) {
        var anchoTexto = doc.getTextWidth(texto);
        var xCentrado = (anchoPagina - anchoTexto) / 2;
        doc.text(xCentrado, y, texto);
    }

    //LOGO
    var img = new Image();
    img.src = '../' + datos[4].log_img;

    img.onload = function () {
        var logoX = 20;
        var logoY = 13;
        doc.addImage(img, 'PNG', logoX, logoY, 50, 30); // Ajusta ancho y alto

        // Encabezado centrado
        textoCentrado("Nombre de la Agencia: " + datos[4].nom_agencia, margenSuperior);
        textoCentrado("Institución: " + datos[4].nomb_comple, margenSuperior + 5);
        textoCentrado("Dirección: " + datos[4].muni_lug, margenSuperior + 10);
        textoCentrado("Email: " + datos[4].emai, margenSuperior + 15);
        textoCentrado("Teléfonos: " + datos[4].tel_1 + " / " + datos[4].tel_2, margenSuperior + 20);
        textoCentrado("NIT: " + datos[4].nit, margenSuperior + 25);

        // Datos del asociado
        doc.setFont('helvetica', 'bold');
        doc.text(margenIzquierdo + 5, 50, 'Nombre del Asociado:');
        doc.text(margenIzquierdo + 5, 55, "DPI:");
        doc.text(margenIzquierdo + 100, 55, 'Teléfono:');
        doc.text(margenIzquierdo + 5, 60, 'Dirección:');
        doc.text(margenIzquierdo + 5, 65, 'Cuenta de ahorro:');
        doc.text(margenIzquierdo + 5, 75, 'Monto:');
        doc.text(margenIzquierdo + 5, 80, 'Monto en letras:');
        doc.text(margenIzquierdo + 5, 90, 'Plazo:');
        doc.text(margenIzquierdo + 100, 90, 'Interés:');
        doc.text(margenIzquierdo + 5, 95, 'Fecha de Apertura:');
        doc.text(margenIzquierdo + 100, 95, 'Fecha de Vencimiento:');

        // Rellenar datos
        doc.setFont('helvetica', 'normal');
        doc.text(margenIzquierdo + 55, 50, datos[0][1]); // Nombre
        doc.text(margenIzquierdo + 25, 55, datos[0][4]); // DPI
        doc.text(margenIzquierdo + 125, 55, datos[0][5]); // Teléfono
        doc.text(margenIzquierdo + 30, 60, datos[0][3]); // Dirección
        doc.text(margenIzquierdo + 40, 65, datos[0][2]); // Cuenta
        doc.text(margenIzquierdo + 25, 75, 'Q.' + intmonto); // Monto
        doc.text(margenIzquierdo + 40, 80, datos[0][6]); // Monto en letras
        doc.text(margenIzquierdo + 25, 90, datos[0][8] + ' días'); // Plazo
        doc.text(margenIzquierdo + 125, 90, intere + '%'); // Interés
        doc.text(margenIzquierdo + 45, 95, fechaini); // Fecha inicio
        doc.text(margenIzquierdo + 150, 95, fechafin); // Fecha vencimiento

        // Beneficiarios (opcional)
        if (datos[1].length > 0) {
            doc.setFont('helvetica', 'bold');
            doc.text(margenIzquierdo + 75, 105, 'DATOS DE BENEFICIARIOS');
            doc.text(margenIzquierdo + 5, 110, 'Nombre Completo');
            doc.text(margenIzquierdo + 90, 110, 'Parentesco');
            doc.text(margenIzquierdo + 150, 110, 'DPI');

            let yPos = 115;
            for (let i = 1; i < datos[1].length; i++) {
                doc.setFont('helvetica', 'normal');
                doc.text(margenIzquierdo + 5, yPos, datos[1][i]['nombre']);
                doc.text(margenIzquierdo + 90, yPos, datos[1][i]['codparent']);
                doc.text(margenIzquierdo + 150, yPos, datos[1][i]['dpi']);
                yPos += 5;
            }
        }


        doc.autoPrint();
        window.open(doc.output('bloburl'))
    };
}

function impresion_certificado_otziles(datos) {
    // // console.log(datos);
    // console.log(datos[1]);
    alert("Impresion de certificado");
    var opciones = {
        orientation: "p",
        unit: "mm",
        format: [240, 300],
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(8);

    // doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);

    var oficina = datos[0][17];
    var recibo = datos[0][18];
    // doc.setFontStyle('bold');
    const y = 40;
    const x = -5;
    //texto normal
    //primeralinea
    doc.setFont('helvetica', 'normal');
    doc.text(x + 60, y + 7, datos[0][2]); //cuenta
    doc.text(x + 140, y + 7, datos[0][4]); //Dpi
    //segundalinea
    doc.text(x + 62, y + 13, datos[0][1]); //nombre del cliente
    doc.text(x + 205, y + 13, intere + '%');//interes
    //terceralinea
    doc.text(x + 70, y + 19, datos[0][3]); //Direccion
    doc.text(x + 200, y + 19, datos[0][5]); //telefono
    //cuartalinea
    doc.text(x + 60, y + 25, datos[0][6]);// monto en letras
    doc.text(x + 135, y + 25, intmonto);//monto en numeros
    doc.text(x + 205, y + 25, datos[0][8] + ' días.'); //PLAZO
    //quintalinea
    doc.text(x + 80, y + 31.5, fechaini);//FECHA INICIO
    doc.text(x + 205, y + 31.5, fechafin);//FECHA vence

    // BENEFICIARIOS
    var i = 1;
    var ini = 125;

    while (i < datos[1].length) {
        var nombres = [];
        var dpis = [];
        var direcciones = [];
        var parentescos = [];
        var porcentajes = [];

        // Recorre datos y agrupa
        for (var j = i; j < i + 5 && j < datos[1].length; j++) {
            nombres.push(datos[1][j]["nombre"] || "Sin nombre");
            parentescos.push(" " + (datos[1][j]["codparent"] || "N/A"));
            dpis.push(" " + (datos[1][j]["dpi"] || "N/A"));
            direcciones.push(" " + (datos[1][j]["direccion"] || "Sin dirección"));
            porcentajes.push(" " + (datos[1][j]["porcentaje"] ? datos[1][j]["porcentaje"] + "%" : "N/A"));
        }

        // Coordenadas iniciales
        var baseXNombre = 27;
        var baseXParentesco = 100;
        var baseXDPI = 170;
        var baseXDireccion = 100;
        var baseXPorcentaje = 210;
        var baseY = 84;
        var lineHeight = 6;

        // Mostrar nombres, parentescos, DPI y direcciones
        nombres.forEach((nombre, index) => {
            var posY = baseY + (index * lineHeight);
            if (posY > 280) { // Límite de la página
                doc.addPage();
                posY = 18; // Reinicia baseY para la nueva página
            }
            doc.text(baseXNombre, posY, nombre); // Nombre
            // doc.text(baseXParentesco, posY, parentescos[index]); // Parentesco
            doc.text(baseXDPI, posY, dpis[index]); // DPI
            doc.text(baseXDireccion, posY, direcciones[index]); // Dirección
            doc.text(baseXPorcentaje, posY, porcentajes[index]);
        });

        // Ajusta la posición baseY para el siguiente grupo
        baseY += nombres.length * lineHeight + 10;

        i += 5;
    }
    // Recibo
    // doc.text(150, 159, "Recibo: " + recibo);
    var lugaroficina = (oficina == "001") ? "Camino Oratorio, Aldea Vasquez, Totonicapán, " : "Camino Oratorio, Aldea Vasquez, Totonicapán, ";
    doc.text(x + 45, y + 66.5, lugaroficina + datos[0][15]);  //lugar y fecha

    doc.autoPrint();
    window.open(doc.output("bloburl"));
}

function impresion_certificado_ixoj(datos) {
    // // console.log(datos);
    // console.log(datos[1]);
    alert("Impresion de certificado");
    var opciones = {
        orientation: "p",
        unit: "mm",
        format: [240, 300],
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(10);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);

    var oficina = datos[0][17];
    var recibo = datos[0][18];
    // doc.setFontStyle('bold');
    const y = 37;
    const x = -5;
    //texto normal

    doc.setFont('helvetica', 'normal');
    //doc.text( x+60, y+8, datos[0][2]); //cuenta
    doc.text(x + 129, y + 6.6, datos[3]); //codcli
    doc.text(x + 42, y + 24, datos[0][1]); //nombre del cliente
    doc.text(x + 30, y + 35.5, datos[0][4]); //Dpi

    doc.text(x + 53, y + 47.5, datos[0][6]);// monto en letras
    doc.text(x + 28, y + 59.5, intmonto);//monto en numeros
    doc.text(x + 133, y + 59.5, fechafin);//FECHA vence

    doc.text(x + 55, y + 70.6, datos[0][8] + ' días.'); //PLAZO
    doc.text(x + 59, y + 82.5, intere + '%');//interes

    //terceralinea
    //doc.text( x+70, y+20, datos[0][3]); //Direccion
    //doc.text( x+210,y+20, datos[0][5]); //telefono
    //cuartalinea
    //quintalinea
    // doc.text( x+80, y+31, fechaini);//FECHA INICIO

    // BENEFICIARIOS
    var i = 1;
    var ini = 125;

    while (i < datos[1].length) {
        var nombres = [];
        var dpis = [];
        var direcciones = [];
        var parentescos = [];
        var porcentajes = [];

        // Recorre datos y agrupa
        for (var j = i; j < i + 5 && j < datos[1].length; j++) {
            nombres.push(datos[1][j]["nombre"] || "Sin nombre");
            parentescos.push(" " + (datos[1][j]["codparent"] || "N/A"));
            dpis.push(" " + (datos[1][j]["dpi"] || "N/A"));
            direcciones.push(" " + (datos[1][j]["direccion"] || "Sin dirección"));
            porcentajes.push(" " + (datos[1][j]["porcentaje"] ? datos[1][j]["porcentaje"] + "%" : "N/A"));
        }

        // Coordenadas iniciales
        var baseXNombre = 18;
        var baseXParentesco = 100;
        var baseXDPI = 96;
        var baseXDireccion = 100;
        var baseXPorcentaje = 215;
        var baseY = 156;
        var lineHeight = 5;

        // Mostrar nombres, parentescos, DPI y direcciones
        nombres.forEach((nombre, index) => {
            var posY = baseY + (index * lineHeight);
            if (posY > 280) { // Límite de la página
                doc.addPage();
                posY = 20; // Reinicia baseY para la nueva página
            }
            doc.text(baseXNombre, posY, nombre); // Nombre
            // doc.text(baseXParentesco, posY, parentescos[index]); // Parentesco
            doc.text(baseXDPI, posY, dpis[index]); // DPI
            //doc.text(baseXDireccion, posY, direcciones[index]); // Dirección
            //doc.text(baseXPorcentaje, posY, porcentajes[index]);
        });

        // Ajusta la posición baseY para el siguiente grupo
        baseY += nombres.length * lineHeight + 10;

        i += 5;
    }
    // Recibo
    // doc.text(150, 159, "Recibo: " + recibo);
    var lugaroficina = (oficina == "001") ? "08 Avenida Cantón Xolacuj Nebaj, Quiché, " : "08 Avenida Cantón Xolacuj Nebaj, Quiché, ";
    doc.text(x + 52, y + 135.5, lugaroficina + datos[0][15]);  //lugar y fecha

    doc.autoPrint();
    window.open(doc.output("bloburl"));
}

function impresion_libreta_ixoj(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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
            transaccion = "RETIRO/DEBITO";
            doc.text(30, pos, transaccion);
            doc.text(88, pos, "");
            doc.text(67, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "DEPOSITO/INTERES";

            doc.text(30, pos, transaccion);
            doc.text(98, pos, "" + pad(currency(monto)));
            doc.text(64, pos, "");
        }
        doc.text(130, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            // console.log("aki papu");
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }
    // console.log(numi);
    // console.log(nfront);
    if (numi > nfront) {
        console.log("aki papu 2");
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

function impresion_recibo_dep_ret_ixoj(datos) {
    // console.log(datos);
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

        if (datos[23] == 'D') {

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

            if (datos[28] == 'Ahorro corriente') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 143, 87, datos[3]);
                doc.text(margenizquierdo + 143, 129, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro programado') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 143, 100, datos[3]);
                doc.text(margenizquierdo + 143, 129, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro infanto juvenil') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 143, 106, datos[3]);
                doc.text(margenizquierdo + 143, 129, datos[3]); // TOTAL
            }

            doc.setFontSize(10);
            let saldoActual1 = parseFloat(datos[24]); // SALDO ACTUAL
            let deposito = parseFloat(datos[25]); // SALDO ANTERIOR
            let saldoAnterior1 = saldoActual1 - deposito;
            doc.text(30, 102, ' ' + saldoAnterior1); //SALDO ANTERIOR
            doc.text(30, 108, ' ' + datos[3]); //ABONO
            doc.text(30, 115, ' ' + datos[24]); //SALDO ACTUAL
        }

        if (datos[23] == 'R') {

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

            if (datos[28] == 'Ahorro corriente') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 150, 80, datos[3]);
                doc.text(margenizquierdo + 150, 118, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro programado') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 150, 93, datos[3]);
                doc.text(margenizquierdo + 150, 118, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro infanto juvenil') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 150, 100, datos[3]);
                doc.text(margenizquierdo + 150, 118, datos[3]); // TOTAL
            }

            let saldoActual = parseFloat(datos[24]);
            let retiro = parseFloat(datos[25]);
            let saldoAnterior = saldoActual + retiro;
            ini = ini + 6;
            doc.text(35, 105, ' ' + saldoAnterior); //SALDO ANTERIOR
            doc.text(35, 111, ' ' + datos[3]); //ABONO
            doc.text(35, 118, ' ' + datos[24]); //SALDO ACTUAL
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

function impresion_certificadofijo_tikal(datos) {
    // // console.log(datos);
    // return;
    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma,$idcli
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    //console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);

    doc.setLineWidth(1); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    //let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    let monto = Number(datos[0][7]); // Obtener el número
    let intmonto = monto.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);
    var coduser = datos[3];
    const y = -16;
    const x = -10;
    //texto en negrita
    doc.setFont('helvetica', 'normal');

    //PRIMERA LINEA
    doc.text(30, y + 65, datos[0][1]);//nombre del cliente
    doc.text(128, y + 65, coduser);//No asociado
    doc.text(182, y + 65, datos[0][2]);//No Cuenta

    //SEGUNDA LINEA
    doc.text(34, y + 74, datos[0][3]);//Direccion
    doc.text(133, y + 74, datos[0][4]);//DPI
    doc.text(190, y + 74, datos[0][5]);//Telefono

    //TERCERA LINEA
    doc.text(41, y + 82, datos[0][6]);//Cantidad en letras
    doc.text(175, y + 82, intmonto);//Cantidad en numeros

    //CUARTA LINEA
    doc.text(34, y + 91, datos[0][8] + ' días.');//Plazo
    doc.text(150, y + 91, fechaini);//Fecha de Deposito

    //QUINTA LINEA
    doc.text(55, y + 99, fechafin);//Fecha de Vencimiento
    doc.text(180, y + 99, intere + '');//Tasa de interes

    //BENEFICIARIOS
    if (!datos[1][1] || datos[1][1].length === 0) {
        //alert('La posición 1 está vacía o no contiene datos válidos');
    } else {
        doc.text(35, y + 113, datos[1][1]['nombre']);  // Nombre
        doc.text(35, y + 122, datos[1][1]['dpi']);     // No DPI
        doc.text(35, y + 130, datos[1][1]['direccion']);// DIRECCION
        doc.text(35, y + 139, datos[1][1]['codparent']);// Parentesco
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

//certificado Primavera
function impresion_certificado_primavera(datos) {
    // // console.log(datos);
    // return;
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);
    doc.text(10, 30, 'Nombre del Asociado  :' + datos[0][1]);
    // doc.text(97, 28, 'Cuenta ' + datos[0][2]);
    doc.text(10, 40, 'Dpi ' + datos[0][4]);
    doc.text(10, 50, 'Direccion ' + datos[0][3]);
    doc.text(10, 60, 'Telefono: ' + datos[0][5]);
    var montoFormateado = Number(datos[0][7]).toLocaleString('es');
    doc.text(10, 70, 'Monto: ****' + montoFormateado + '****');
    doc.text(10, 80, 'La cantidad de ' + datos[0][6]);
    doc.text(10, 90, 'plazo ' + datos[0][8]);
    doc.text(10, 100, 'Fecha Inicio ' + datos[0][9]);
    doc.text(10, 110, 'Fecha Vencimiento ' + datos[0][10]);
    doc.text(10, 120, 'Tasa Interes ' + datos[0][11]);
    doc.text(70, 120, 'Interes calcu ' + datos[0][12].toFixed(2));
    doc.text(130, 120, 'IPF ' + datos[0][13].toFixed(2));
    //  doc.text(10, 130, 'totalrecibir ' + datos[0][14].toFixed(2));

    var i = 1;
    var ini = 140;
    while (i < datos[1].length) {
        doc.text(10, ini, 'BENEFICIARIO ' + datos[1][i]['nombre']);

        ini = ini + 10;

        i++;
    }
    doc.text(10, 160, 'lugar y fecha ' + datos[0][15]);



    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
function impresion_certificado_coditoto(datos) {
    // // console.log(datos);
    // return;
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);
    doc.text(20, 70, 'Nombre del Asociado  :' + datos[0][1]);
    doc.text(20, 80, 'Cuenta ' + datos[0][2]);
    doc.text(20, 90, 'Dpi ' + datos[0][4]);
    doc.text(20, 100, 'Direccion ' + datos[0][3]);
    doc.text(20, 110, 'Telefono: ' + datos[0][5]);
    var montoFormateado = Number(datos[0][7]).toLocaleString('es-MX');
    doc.text(20, 120, 'Monto: ****Q.' + montoFormateado + '****');
    doc.text(80, 120, 'plazo ' + datos[0][8]);
    doc.text(20, 130, 'La cantidad de ' + datos[0][6]);
    doc.text(20, 140, 'Fecha Inicio ' + datos[0][9]);
    doc.text(80, 140, 'Fecha Vencimiento ' + datos[0][10]);
    let tasaInteres = parseFloat(datos[0][11]);
    if (!isNaN(tasaInteres)) {
        tasaInteres = tasaInteres.toFixed(2) + '%';
    } else {
        tasaInteres = 'Dato no numérico';
    }
    doc.text(20, 150, 'Tasa Interes ' + tasaInteres);
    doc.text(70, 150, 'Interes calcu Q.' + datos[0][12].toFixed(2));
    doc.text(140, 150, 'IPF Q.' + datos[0][13].toFixed(2));

    doc.text(185, 150, 'Monto a pagar: Q.' + (datos[0][12].toFixed(2) - datos[0][13].toFixed(2)));

    var i = 1;
    var ini = 183;
    while (i < datos[1].length) {
        doc.text(50, ini - 2, '  ' + datos[1][i]['nombre']);
        ini = ini + 10;
        i++;
    }
    var array_fechasol = datos[0][15].split("-")
    var ano = array_fechasol[2];

    var mes = array_fechasol[1];
    var mes_convertido = convertir_mes(mes);
    var dia = array_fechasol[0];

    var espacios = '                      ';

    doc.text(56, 225, 'Cantón Xantún, Totonicapán      ' + ano + ' / ' + mes_convertido + ' / ' + dia);
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

//certificado copefuente
function impresion_certificado_copefuente(datos) {
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    datosAsoc = datos[4];
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var capitalFinal = Number(datos[0][7]) + Number(datos[0][12]) - Number(datos[0][13]);

    doc.line(xInicio, 55, xFinal, 55);
    doc.text(30, 52, 'CUENTA NO.' + datos[0][2]);
    doc.setFontSize(13);
    doc.text(100, 52, 'INFORMACION DE LA CUENTA');
    doc.setFontSize(12);
    //LINEA MARGEN
    doc.text(15, 65, 'NO DE CUENTA ');
    doc.text(15, 70, 'ASOCIADO');
    doc.text(15, 75, 'TIPO DE CUENTA');
    doc.text(15, 80, 'TASA DE INTERES');
    doc.text(15, 85, 'FECHA APERTURA');
    doc.text(15, 90, 'BALANCE');
    //columna 2
    doc.text(80, 65, datos[0][2]);
    doc.text(80, 70, unescape(unescape(datos[0][1])));
    doc.text(80, 75, 'Ahorro a Plazo Fijo');
    doc.text(80, 80, vdecimal + ' %');
    doc.text(80, 85, datos[0][9]);
    doc.text(80, 90, 'Q.' + datos[0][7]);

    doc.setFontSize(13);
    doc.text(70, 102, 'INFORMACION DEL DEPOSITO A PLAZO FIJO ');
    doc.line(xInicio, 105, xFinal, 105);
    doc.setFontSize(12);


    doc.text(15, 116, 'Fecha inicial: ' + datos[0][9]);
    doc.text(15, 122, 'Capital inicial: ' + datos[0][7]);
    doc.text(15, 128, 'Capital inicial en letras: ' + datos[0][6]);
    doc.text(15, 134, 'Interes generado:' + vinteres);
    doc.text(75, 134, 'Ipf:' + ipdf);
    doc.text(115, 134, 'tasa ' + vdecimal + ' %');


    doc.setFontStyle('bold');
    doc.text(80, 140, 'DOCUMENTO NO NEGOCIABLE ');
    doc.setFontStyle('normal');

    doc.text(100, 115, 'Fecha de vencimiento: ' + datos[0][10]);
    doc.text(100, 122, 'Capital final: Q' + capitalFinal.toFixed(2));
    doc.setFontSize(13);
    doc.text(15, 148, 'BENEFICIARIO DE LA CUENTA ');
    doc.setFontSize(12);
    doc.line(xInicio, 150, xFinal, 150);
    var i = 1;
    var ini = 158;
    while (i < datos[1].length) {
        doc.text(15, ini, 'Nombres ' + datos[1][i]['nombre']);
        doc.text(90, ini, 'Dpi ' + datos[1][i]['dpi']);
        doc.text(140, ini, 'Parentesco ' + datos[1][i]['codparent']);
        ini = ini + 5;
        doc.text(15, ini, 'Direcciones ' + datos[1][i]['direccion']);

        ini = ini + 10;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_certificado_cooprode(datos) {
    // alert("Impresión de certificado");
    // // console.log(datos);
    try {
        var opciones = {
            orientation: 'p',
            unit: 'mm',
            format: [240, 300]
        };
        //req 1 ajuste de certificados
        var doc = new jsPDF(opciones);
        doc.setFontSize(12);
        var direcciondefaut = ' ';
        var fechaActual = new Date();
        var dia = fechaActual.getDate();
        var mes = fechaActual.getMonth() + 1; // Los meses van de 0-11, por eso se suma 1
        var anio = fechaActual.getFullYear();

        doc.text(50, 35, ' ' + direcciondefaut);
        doc.text(95, 25, ' ' + dia + '                ' + mes + '           ' + anio); //fecha actual
        doc.text(40, 40, ' ' + datos[0][2]);//cuentya
        doc.text(40, 48, ' ' + datos[0][1]);//titular
        doc.text(46, 54, ' ' + datos[0][6]);//cantidad en letras
        // doc.text(100, 73, '' + datos[0][7]);//

        // regresa valores dia/mes/a;o (fecha deposito)
        var fechaDeposito = datos[0][9] || '';
        var FechaDeposito = '';
        if (fechaDeposito) {
            var partesFechaDeposito = fechaDeposito.split('-');
            if (partesFechaDeposito.length === 3) {
                FechaDeposito = partesFechaDeposito[2] + '-' + partesFechaDeposito[1] + '-' + partesFechaDeposito[0];
            } else {
                FechaDeposito = fechaDeposito;
            }
        }
        doc.text(115, 68, ' ' + FechaDeposito); // fecha deposito

        // regresa valores dia/mes/a;o (fecha vencimiento)
        var dateorigin = datos[0][10] || '';
        var nuevaFecha = '';
        if (dateorigin) {
            var partesFecha = dateorigin.split('-');
            if (partesFecha.length === 3) {
                nuevaFecha = partesFecha[2] + '-' + partesFecha[1] + '-' + partesFecha[0];
            } else {
                nuevaFecha = dateorigin;
            }
        }
        doc.text(55, 80, ' ' + nuevaFecha);//fecha vencimiento
        doc.text(170, 80, ' ' + Math.floor(Number(datos[0][11] || 0))); // tasa

        var i = 1;
        var ini = 130;
        if (Array.isArray(datos[1])) {
            while (i < datos[1].length) {
                var ben = datos[1][i] || {};
                // doc.text(10, ini, 'Nombres: ' + (ben['nombre'] || ''));
                // ini = ini + 10;
                // doc.text(10, ini, 'Dpi: ' + (ben['dpi'] || ''));
                // doc.text(90, ini, 'Nacimiento: ' + (ben['fecnac'] || ''));
                // ini = ini + 10;
                // doc.text(10, ini, 'Direccion: ' + (ben['direccion'] || ''));
                // ini = ini + 10;
                // doc.text(10, ini, 'Parentesco: ' + (ben['codparent'] || ''));
                // ini = ini + 40;
                i++;
            }
        }

        doc.autoPrint();
        window.open(doc.output('bloburl'));
    } catch (err) {
        console.error('impresion_certificado_cooprode error:', err);
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Ocurrió un error al generar el certificado.'
            });
        } else {
            alert('Ocurrió un error al generar el certificado.');
        }
    }
}

//CORPOCREDIT
function impresion_certificado_corpocredit(datos) {
    alert("Impresion de certificado");
    console.log(datos);
    var opciones = {
        orientation: "p",
        unit: "mm",
        format: [240, 300],
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(11);

    var oficina = datos[0][17];
    var recibo = datos[0][18];
    // doc.setFontStyle('bold');
    doc.text(159, 34, " " + datos[0][0]); //NO.
    doc.text(65, 34, 'C.I. ' + datos[0][16]); //C.I.
    doc.text(163, 90, " " + datos[0][2]); //CUENTA
    doc.text(38, 90, " " + datos[0][1]); //Nombre
    doc.text(39, 97, " " + datos[0][3]); //Direccion
    doc.text(120, 97, " " + datos[0][4]); //Dpi
    doc.text(163, 97, "  " + datos[0][5]); //Tel

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

    doc.text(49, 104, " " + datos[0][6] + '  ' + ptdecimal + '/100'); // La cantidad en letras

    doc.text(34, 111, " " + datos[0][7]); // Monto
    doc.text(83, 111, " " + datos[0][8]); //plazo
    doc.text(155, 111, " " + datos[0][9]); //Fec.deposito
    //listo

    doc.text(58, 118, " " + datos[0][10]); //Fec. vencimiento

    var tasaFormateada = parseFloat(datos[0][11]).toFixed(2) + "%";
    doc.text(140, 118, " " + tasaFormateada); // tasa

    // Restablece el estilo de fuente a normal
    doc.setFontStyle("normal");

    doc.text(60, 126, " " + parseFloat(datos[0][12]).toFixed(2)); // Interes calcu
    doc.text(121, 126, " " + parseFloat(datos[0][13]).toFixed(2)); // ipf
    doc.text(167, 126, " " + parseFloat(datos[0][14]).toFixed(2)); //totalrecibir


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
            nombres.push(datos[1][j]["nombre"]);//nombres
            dpis.push(" " + datos[1][j]["dpi"]);//Dpi.
            direcciones.push(" " + datos[1][j]["direccion"]);//Direccion
            parentescos.push(" " + datos[1][j]["codparent"]); //Parentesco
        }
        fechasNacimiento.push(" " + datos[1][i]["fecnac"]); //Nacimiento
        doc.text(60, 136, " " + nombres.join(", ")); // Nombres
        doc.text(45, 143, " " + dpis.join(", ")); // DPI
        doc.text(160, 143, " " + fechasNacimiento.join(" ")); // Fechas de Nacimiento
        doc.text(50, 152, " " + direcciones.join(", ")); // Direcciones
        doc.text(50, 159, " " + parentescos.join(", ")); // Parentescos
        ini += 30;
        i += 5;
    }
    doc.text(150, 159, "Recibo: " + recibo);
    var lugaroficina = (oficina == "002") ? "Santa Cruz" : ((oficina == "003") ? "Cunén" : "Nebaj");

    doc.text(55, 244, lugaroficina + ", Quiché, " + datos[0][15]);  //lugar y fecha

    doc.autoPrint();
    window.open(doc.output("bloburl"));
}

function certificado_aho_coopeadg(datos) {

    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(11);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);

    const y = 75;
    const x = 10;
    //texto en negrita
    doc.setFont('helvetica', 'bold');
    doc.text(x + 28, y - 25, 'Nombre del Asociado:');
    doc.text(x + 28, y - 21, 'Cuenta No.');
    doc.text(x + 19, y - 11, "DPI:");
    doc.text(x + 19, y - 6, 'Dirección:');
    doc.text(x + 20, y + 5, 'Monto:');
    doc.text(x + 20, y + 10, 'Monto en letras:');
    doc.text(x + 95, y - 11, 'Teléfono:');
    doc.text(x + 20, y + 20, 'Plazo:');
    doc.text(x + 85, y + 20, 'Interes:');
    doc.text(x + 20, y + 24, 'Fecha de Apertura:');
    doc.text(x + 85, y + 24, 'Fecha de Vencimiento:');

    //texto normal
    doc.setFont('helvetica', 'normal');
    doc.text(x + 70, y - 25, datos[0][1]); //nombre del cliente
    doc.text(x + 70, y - 21, datos[0][2]); //cuenta
    doc.text(x + 29, y - 11, datos[0][4]); //Dpi
    doc.text(x + 39, y - 6, datos[0][3]); //Direccion
    doc.text(x + 35, y + 5, 'Q.' + intmonto);//monto +15 de texto
    doc.text(x + 52, y + 10, datos[0][6]);// monto en letras
    doc.text(x + 114, y - 11, datos[0][5]); //telefono
    doc.text(x + 32, y + 20, datos[0][8] + ' días.');//PLAZO
    doc.text(x + 100, y + 20, intere + '%');//interes
    doc.text(x + 56, y + 24, fechaini);//FECHA INICIO
    doc.text(x + 128, y + 24, fechafin);//FECHA vence

    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    const max_caracteres = 10;
    console.log(datos[1]);
    for (let i = 1; i < datos[1].length; i++) {
        beneficiarios = beneficiarios + datos[1][i]['nombre'];
        dpi = dpi + datos[1][i]['dpi'];
        decripc = decripc + datos[1][i]['codparent'];
        tel = tel + datos[1][i]['telefono'];
        if (i !== ((datos[1].length) - 1)) {
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
    doc.text(x + 55, y + 35, 'DATOS DE BENEFICIARIO ');
    doc.setFont('helvetica', 'normal');

    doc.setFont('helvetica', 'bold');
    doc.text(x + 20, y + 40, 'Nombre Completo');
    doc.text(x + 95, y + 40, 'Parentesco');
    doc.text(x + 130, y + 40, 'DPI');
    // doc.text(175,y+ 40, 'NO. TELEFONO');
    doc.setFont('helvetica', 'normal');

    doc.text(x + 20, y + 44, splitTitle); //beneficiario
    doc.text(x + 95, y + 44, decripc2); //parentezco
    doc.text(x + 120, y + 44, dpi2); //DPI beneficiario
    // doc.text(175,y+ 44, tel2); //telefono

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}


function certificado_aho_altascumbres(datos) {

    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(13);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);

    const y = 45;
    const x = -10;
    //texto en negrita
    doc.setFont('helvetica', 'bold');
    doc.text(x + 28, y + 10, 'Nombre del Asociado:');
    doc.text(x + 28, y + 20, 'Cuenta No.');
    doc.text(x + 19, y + 35, "DPI:");
    doc.text(x + 95, y + 35, 'Teléfono:');

    doc.text(x + 19, y + 45, 'Dirección:');
    doc.text(x + 20, y + 60, 'Monto:');
    doc.text(x + 20, y + 70, 'Monto en letras:');

    doc.text(x + 20, y + 85, 'Plazo:');
    doc.text(x + 95, y + 85, 'Interes:');
    doc.text(x + 20, y + 95, 'Fecha de Apertura:');
    doc.text(x + 95, y + 95, 'Fecha de Vencimiento:');

    //texto normal
    doc.setFont('helvetica', 'normal');
    doc.text(x + 85, y + 10, datos[0][1]); //nombre del cliente
    doc.text(x + 85, y + 20, datos[0][2]); //cuenta
    doc.text(x + 29, y + 35, datos[0][4]); //Dpi
    doc.text(x + 120, y + 35, datos[0][5]); //telefono

    doc.text(x + 50, y + 45, datos[0][3]); //Direccion
    doc.text(x + 35, y + 60, 'Q.' + intmonto);//monto +15 de texto
    doc.text(x + 60, y + 70, datos[0][6]);// monto en letras

    doc.text(x + 35, y + 85, datos[0][8] + ' días.'); //PLAZO
    doc.text(x + 115, y + 85, intere + '%');//interes
    doc.text(x + 65, y + 95, fechaini);//FECHA INICIO
    doc.text(x + 148, y + 95, fechafin);//FECHA vence

    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    const max_caracteres = 10;
    // console.log(datos[1]);
    for (let i = 1; i < datos[1].length; i++) {
        beneficiarios = beneficiarios + datos[1][i]['nombre'];
        dpi = dpi + datos[1][i]['dpi'];
        decripc = decripc + datos[1][i]['codparent'];
        tel = tel + datos[1][i]['telefono'];
        if (i !== ((datos[1].length) - 1)) {
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

    console.log(datos[1].length)

    if (datos[1].length > 0) {
        doc.setFont('helvetica', 'bold');
        doc.text(x + 55, y + 120, 'DATOS DE BENEFICIARIOS');
        doc.setFont('helvetica', 'normal');

        doc.setFont('helvetica', 'bold');
        doc.text(x + 20, y + 130, 'Nombre Completo');
        doc.text(x + 95, y + 140, 'Parentesco');
        doc.text(x + 130, y + 130, 'DPI');
        // doc.text(175,y+ 40, 'NO. TELEFONO');
        doc.setFont('helvetica', 'normal');

        doc.text(x + 20, y + 140, splitTitle); //beneficiario
        doc.text(x + 95, y + 140, decripc2); //parentezco
        doc.text(x + 120, y + 140, dpi2); //DPI beneficiario
        // doc.text(175,y+ 44, tel2); //telefono
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
function certificado_aho_coopeadif(datos) {

    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(13);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');

    var intere = parseFloat(datos[0][11]);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);

    const y = 75;
    const x = 20;
    //texto en negrita
    doc.setFont('helvetica', 'bold');

    doc.setFont('helvetica', 'normal');
    doc.text(x + 12, y + 22, datos[0][1]); //nombre del cliente
    // doc.text( x+85, y+20, datos[0][2]); //cuenta
    doc.text(x + 77, y + 28, datos[0][4]); //Dpi

    doc.text(x + 50, y + 35, datos[0][6]);// monto en letras

    doc.text(x + 12, y + 42, 'Q.' + intmonto);//monto +15 de texto
    doc.text(x + 75, y + 47, intere + '%');//interes

    var direccion = (datos[0][24] == 2) ? "Santa Clara, Totonicapán" : "Aldea Vásquez, Totonicapán"

    var cantmeses = Math.ceil(datos[0][8] / 30);

    doc.text(x + 178, y + 47, cantmeses + ' meses'); //PLAZO
    // doc.text( x+150, y+60, datos[0][8] + ' días.'); //PLAZO
    doc.text(x + 50, y + 55, fechafin);//FECHA vence

    // doc.text( x+65, y+200,datos[0][3]+' '+ dia + ' de '+mes_convertido+' de '+ano );//FECHA INICIO
    doc.text(x + 70, y + 194, direccion + ' ' + datos[0][22]);

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function certificado_aho_multinorte(datos) {
    // // console.log(datos);
    // return;
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);
    doc.text(55, 35, 'Cuenta: ' + datos[0][2]);
    doc.text(55, 40, "Nombre del Asociado: " + datos[0][1]); //nombre

    let montoFormateado = Number(datos[0][7]).toLocaleString('es');
    doc.text(30, 62, 'Monto Inversion: ' + datos[0][6] + '-EXACTOS');
    doc.text(160, 70, 'Monto: ****' + montoFormateado + '****');
    let monto = Number(datos[0][7]);
    let tasa = parseFloat(datos[0][11])
    let tasaFormateada = parseFloat(datos[0][11]).toFixed(2) + "%";
    doc.text(30, 70, "Tasa Interes: " + tasaFormateada); // tasa
    let muultitasa = monto * (tasa / 100)
    let resultadoFormateado = muultitasa.toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    doc.text(160, 78, "Pago de Interes: ****" + resultadoFormateado + "****"); // tasa
    doc.text(30, 78, 'Plazo de Inversion: ' + datos[0][8]);
    doc.text(160, 86, 'Fecha de Vencimiento: ' + datos[0][10]);
    doc.text(30, 86, 'Elaborado por: ' + datos[0][19] + " " + datos[0][20]);
    doc.text(30, 102, '________________________ ');
    // doc.text(100,102  , '________________________ ' );
    doc.text(170, 102, '________________________ ');
    doc.text(30, 108, '     GERENTE GENERAL ');
    // doc.text(100,108  , '       REPRESENTANTE ' );
    // doc.text(100,113  , '                  LEGAL ' );
    doc.text(170, 108, '              CLIENTE ');
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_certificado_copibelen(datos) {
    // // console.log(datos);
    // return;
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);


    doc.text(30, 56, 'NOMBRE: ' + datos[0][1]);
    doc.text(130, 45, 'No. Certificado' + datos[0][0]);
    doc.text(30, 45, 'Cuenta: ' + datos[0][2]);
    let montoFormateado = Number(datos[0][7]).toLocaleString('es');
    doc.text(20, 70, 'Monto Inversion: ' + datos[0][6] + '-EXACTOS');
    doc.text(130, 82, 'Monto: ****' + montoFormateado + '****');
    let monto = Number(datos[0][7]);
    let tasa = parseFloat(datos[0][11])
    let tasaFormateada = parseFloat(datos[0][11]).toFixed(2) + "%";
    doc.text(20, 82, "Tasa Interes: " + tasaFormateada); // tasa
    // let muultitasa = monto * (tasa/100)
    let interescalculado = datos[0][12];
    let isr = datos[0][13];
    let interesfinal = interescalculado - isr;
    let resultadoFormateado = interesfinal.toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    doc.text(130, 90, "Pago de Interes: ****" + resultadoFormateado + "****"); // tasa
    doc.text(20, 90, 'Plazo de Inversion: ' + datos[0][8]);
    doc.text(130, 98, 'Fecha de Vencimiento: ' + datos[0][10]);
    doc.text(20, 106, 'Elaborado por: ' + datos[0][19] + " " + datos[0][20]);
    doc.text(20, 128, '________________________ ');
    doc.text(90, 128, '________________________ ');
    doc.text(160, 128, '________________________ ');
    doc.text(20, 134, '     GERENTE GENERAL ');
    doc.text(90, 134, '       REPRESENTANTE ');
    doc.text(100, 139, '          LEGAL ');
    doc.text(160, 134, '              CLIENTE ');
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}


function impresion_certificado_copeplus(datos) {
    // console.log(datos);
    // return;
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(10);
    let harvey = 18;


    doc.text(49, harvey - 1, datos[0][1]); // name
    doc.text(170, harvey, datos[0][4]); // DPI
    doc.text(38, harvey + 5, datos[0][2]);
    doc.text(170, harvey + 7, datos[0][5]);
    doc.text(38, harvey + 75, "Totonicapan, " + datos[0][23]);


    doc.text(35, harvey + 12, datos[0][3]); // address
    let montoFormateado = Number(datos[0][7]).toLocaleString('es');
    doc.text(35, harvey + 18, '****Q.' + montoFormateado + '****');

    doc.text(174, harvey + 15, datos[0][8]); // plazo
    doc.setFontSize(8);
    doc.text(94, harvey + 18, datos[0][6]); // total en letras
    doc.setFontSize(10);
    // Mostrar tipo de acreditación en texto
    let tipoAcreditacion = '';
    if (datos[0][25] === 'M') {
        tipoAcreditacion = 'Mensual';
    } else if (datos[0][25] === 'T') {
        tipoAcreditacion = 'Trimestral';
    } else if (datos[0][25] === 'S') {
        tipoAcreditacion = 'Semestral';
    } else if (datos[0][25] === 'A') {
        tipoAcreditacion = 'Anual';
    } else if (datos[0][25] === 'V') {
        tipoAcreditacion = 'Vencimiento';
    } else {
        tipoAcreditacion = datos[0][25] || '';
    }
    doc.text(175, harvey + 22, tipoAcreditacion); // tipo de acreditacion
    let tasaInteres = parseFloat(datos[0][11]);
    if (!isNaN(tasaInteres)) {
        tasaInteres = tasaInteres.toFixed(2) + '%';
    } else {
        tasaInteres = 'Dato no numérico';
    }
    doc.text(179, harvey + 29, tasaInteres); // tasa interés

    doc.text(47, harvey + 26, datos[0][9]);   //fecha dep
    doc.text(128, harvey + 27, datos[0][10]);//fecha v


    // doc.text(130, 150, 'IPF Q.' + datos[0][13].toFixed(2));
    // doc.text(175, 150, 'Monto a pagar: Q.' + (datos[0][12].toFixed(2)-datos[0][13].toFixed(2)));

    var i = 1;
    var ini = 157;
    while (i < datos[1].length) {
        doc.text(35, ini - 93, '  ' + datos[1][i]['nombre']);
        ini = ini + 10;
        doc.text(175, ini - 92, '  ' + datos[1][i]['codparent']);
        ini = ini + 10;
        doc.text(115, ini - 96, '  ' + datos[1][i]['dpi']);
        ini = ini + 10;
        doc.text(35, ini - 112, '  ' + datos[1][i]['direccion']);
        ini = ini + 10;

        i++;
    }
    var array_fechasol = datos[0][15].split("-")
    var ano = array_fechasol[2];

    var mes = array_fechasol[1];
    var mes_convertido = convertir_mes(mes);
    var dia = array_fechasol[0];



    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_certificado_cope27(datos) {
    // console.log(datos);
    //alert("Impresion ");
    var opciones = {
        orientation: 'l',
        unit: 'mm',
        format: [297, 210]
    };
    // var oficina = datos[0][17];
    // var lugaroficina = (oficina == "002") ? "Playa Grande Ixcán, Ixcán, Quiché, " : ((oficina == "003") ? "Santa Cruz Barillas, Huehuetenango, " : "Aldea Mayaland, Ixcán, Quiché, ");

    datosCuenta = datos[4];
    var doc = new jsPDF(opciones);
    doc.setFontSize(10);
    let harvey = 76;
    //primera fila
    doc.text(65, harvey - 2, datos[0][2]); // account
    doc.text(160, harvey - 2, datos[0][4]); // DPI
    // segunda fila
    doc.text(70, harvey + 8, datos[0][1]); // name
    let tasaInteres = parseFloat(datos[0][11]);
    if (!isNaN(tasaInteres)) {
        tasaInteres = tasaInteres.toFixed(2) + '%';
    } else {
        tasaInteres = 'Dato no numérico';
    }
    doc.text(250, harvey + 8, tasaInteres); //tasa de interes anual
    //tercera fila
    doc.text(70, harvey + 18, datos[0][3]); //address
    doc.text(230, harvey + 18, datos[0][5]);
    //cuarta fila
    doc.setFontSize(6);
    doc.text(54, harvey + 26, datos[0][6]); // total en letras
    doc.setFontSize(10);
    let montoFormateado = Number(datos[0][7]).toLocaleString('es');
    doc.text(160, harvey + 26, '****Q.' + montoFormateado + '****');

    doc.text(240, harvey + 26, datos[0][8]); // plazo
    //quinta fila
    doc.text(60, harvey + 36, conviertefecha(datos[0][9])); // fecha apertura
    doc.text(247, harvey + 36, conviertefecha(datos[0][10])); // fecha vence
    doc.text(50, harvey + 100, 'Totonicapán, ' + datosCuenta['fechaletra']);

    var i = 1;
    var ini = 132;
    while (i < datos[1].length) {

        doc.text(20, ini, '  ' + datos[1][i]['nombre']);
        doc.setFontSize(9);
        doc.text(115, ini, '  ' + datos[1][i]['direccion']);
        doc.setFontSize(10);
        doc.text(205, ini, '  ' + datos[1][i]['dpi']);
        doc.text(255, ini, '  ' + datos[1][i]['porcentaje'] + ' %');


        ini = ini + 10;

        i++;
    }


    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_certificado_coinco(datos) {
    // console.log(datos);
    // return;
    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(13);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 20; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);
    const y = 50;
    const x = 0;
    //texto en negrita
    doc.setFontSize(6);
    doc.setFont('helvetica', 'normal');
    doc.text(x + 40, y + 23 , datos[0][1]); //nombre del cliente
    doc.text(x + 125, y + 23, datos[0][2]); //cuenta
    doc.text(x + 88, y + 27, datos[0][4]); //Dpi
    doc.text(x + 122, y + 27, datos[0][5]); //telefono

    doc.setFontSize(4);
    doc.text(x + 40, y + 27, datos[0][3]); //Direccion
    doc.setFontSize(6);
    doc.text(x + 122, y + 31 , intmonto); //monto +15 de texto
    doc.text(x + 45, y + 31 , datos[0][6]); // monto en letras

    doc.text(x + 40, y + 35 , datos[0][8] + ' días.'); //PLAZO
    doc.text(x + 115, y + 39, '' + intere); //interes
    doc.text(x + 115, y + 35 , fechaini); //FECHA INICIO
    doc.text(x + 55, y + 39 , fechafin); //FECHA vence

    // Definir tu párrafo

    // doc.setFontSize(11);
    // doc.setFont('helvetica', 'normal');
    // let parrafo1 = 'La Cooperativa Integtral de Ahorro y Credito "Desarrollo Empresarial del Lago" Responsabilidad Limitada (CODELAGO, R.L.), CERTIFICA: Que ha recibido el ahorro a plazo fijo y entregara al asiociado a partir del día hábil siguiente a la fecha de su vencimiento, la cantidad depositada y los interes devengados.';
    // // Ancho
    // let anchoTexto = 155;
    // let linea1 = doc.splitTextToSize(parrafo1, anchoTexto);
    // let linea2 = doc.splitTextToSize(parrafo2, anchoTexto);

    // // doc.text(linea1, x + 30, y - 1, { align: "justify" });
    // // doc.text(linea2, x + 30, y + 62, { align: "justify" });

    doc.text(x + 55, y + 130, "Chicojl, San Pedro Carchá, Alta Verapaz.     -   " + datos[0][15]); //Fecha de impresion
    // doc.text(x + 30, y + 150, "F.____________________________");
    // doc.text(x + 40, y + 154, "Firma del Asociado");
    // doc.text(x + 120, y + 154, "Representante Legal de la Cooperativa");
    // doc.text(x + 120, y + 150, "F._____________________________");

    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    let direccion = "";
    const max_caracteres = 10;
    // console.log(datos[1]);
    for (let i = 1; i < datos[1].length; i++) {
        beneficiarios = beneficiarios + datos[1][i]['nombre'];
        dpi = dpi + datos[1][i]['dpi'];
        decripc = decripc + datos[1][i]['codparent'];
        tel = tel + datos[1][i]['telefono'];
        direccion = direccion + datos[1][i]['direccion'];
        if (i !== ((datos[1].length) - 1)) {
            beneficiarios = beneficiarios + "; ";
            dpi = dpi + "; ";
            decripc = decripc + "; ";
            tel = tel + "; ";
            direccion = direccion + "; ";
        }

    }


    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    var dpi2 = doc.splitTextToSize(dpi, 180);
    var decripc2 = doc.splitTextToSize(decripc, 180);
    var tel2 = doc.splitTextToSize(tel, 180);
    var direccion2 = doc.splitTextToSize(direccion, 180);

    console.log(datos[1].length)

    if (datos[1].length > 0) {
        doc.setFontSize(6);
        doc.setFont('helvetica', 'normal');
        doc.text(x + 40, y + 48, splitTitle); //beneficiario
        doc.text(x + 40, y + 60, decripc2); //parentezco
        doc.text(x + 40, y + 53,dpi2); //DPI beneficiario
        doc.text(x + 40, y + 57,direccion2); //direccion
        // doc.text(175,y+ 40, 'NO. TELEFONO');
        doc.setFont('helvetica', 'normal');

    }


    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
//#endregion
//#region FUNCIONES PARA FORMATO DE LIQUIDACION DE CERTIFICADO
// MAIN
function impresion_liquidacion_certificado_main(datos) {
    // console.log(datos);
    alert('Insertar hoja de certificado')
    var opciones = {
        orientation: 'P',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);
    var i = 1;
    var ini = 30;

    while (i < 4) {
        doc.text(10, ini, 'Certificado. ' + datos[2]);
        doc.text(97, ini, 'Fecha. ' + datos[3]);
        doc.text(145, ini, 'Codigo de cuenta: ' + datos[4]);

        ini = ini + 10;
        doc.text(10, ini, 'Cliente: ' + datos[5]);

        ini = ini + 10;
        doc.text(10, ini, 'Monto apertura: ' + datos[6]);
        doc.text(130, ini, 'Interes: Q. ' + parseFloat(datos[7]).toFixed(2));

        ini = ini + 12;
        doc.text(10, ini, 'ISR: ' + parseFloat(datos[8]).toFixed(2));
        ini = ini + 10;
        doc.text(10, ini, 'Monto en letras: ' + datos[9]);
        ini = ini + 10;
        doc.text(10, ini, 'Recibo: ' + datos[10]);
        ini = ini + 60;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_liquidacion_certificado_corpocredit(datos) {
    console.log(datos);
    //'Datos', '1', $codcrt, $fechaliquidacion, $codaho, $nombrecli, $montoapr, $interescal, $ipfcalc, $texto_monto, $recibo,$dpi]
    alert('Insertar hoja de certificado')
    var opciones = {
        orientation: 'P',
        unit: 'mm',
        format: [240, 300]
    };
    var fecha = new Date(datos[3]);
    var dia = fecha.getDate() + 1;
    var mes = fecha.getMonth() + 1;
    var ano = fecha.getFullYear();

    var doc = new jsPDF(opciones);
    doc.setFontSize(9);
    var i = 1;
    var ini = 60;
    while (i < 2) {
        const cap_int = parseFloat(datos[6]) + parseFloat(datos[7]);
        const int_isr = parseFloat(datos[7]) - parseFloat(datos[8]);
        const tot_recibe = cap_int - parseFloat(datos[8]);
        doc.text(29, ini, 'Certificado. ' + datos[2]);//CERTIFICADO

        ini = ini + 4;
        doc.text(29, ini, 'Codigo de Cuenta: ' + datos[4]);//CODIGO DE CUENTAS

        ini = ini + 10;
        doc.text(73, ini, ' ' + dia);//DIA
        doc.text(125, ini, ' ' + convertir_mes(mes));//MES
        doc.text(170, ini, ' ' + ano);//AÑO

        ini = ini + 12;
        doc.text(110, ini, ' ' + datos[9]);//MONTO EN LETRAS

        ini = ini + 10;
        doc.text(35, ini, ' ' + tot_recibe);//MONTO APERTURA/CAPITAL

        ini = ini + 12;
        doc.text(73, ini, ' ' + datos[6]);//MONTO APERTURA/CAPITAL 2

        ini = ini + 6;

        doc.text(73, ini, ' ' + datos[7]);//INTERES
        doc.text(108, ini, ' ' + cap_int);//CAPITAL + INTERES

        ini = ini + 6;

        doc.text(108, ini, ' ' + datos[8]);//IPF
        doc.text(172, ini, ' ' + parseFloat(int_isr).toFixed(2));//interes - isr

        ini = ini + 12;

        doc.text(108, ini, ' ' + tot_recibe);//capital e interes menos ipf

        ini = ini + 30;
        doc.text(44, ini, ' ' + datos[5]);//CLIENTE

        ini = ini + 12;
        doc.text(47, ini, ' ' + datos[11]);//DPI

        ini = ini + 12;
        doc.text(49, ini, ' ' + datos[10]);//RECIBO
        ini = ini + 60;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_liquidacion_certificado_primavera(datos) {
    alert('Insertar hoja de certificado')
    var opciones = {
        orientation: 'P',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);
    var ini = 30;
    doc.text(10, ini, 'Certificado. ' + datos[2]);
    doc.text(97, ini, 'Fecha. ' + datos[3]);
    doc.text(145, ini, 'Codigo de cuenta: ' + datos[4]);

    ini = ini + 10;
    doc.text(10, ini, 'Cliente: ' + datos[5]);

    ini = ini + 10;
    doc.text(10, ini, 'Monto apertura: Q.' + datos[6]);
    doc.text(130, ini, 'Interes: Q ' + datos[7]);

    ini = ini + 12;
    doc.text(10, ini, 'ISR: ' + datos[8]);
    ini = ini + 10;
    doc.text(40, ini, 'Monto en letras: ' + datos[9]);
    ini = ini + 10;
    doc.text(10, ini, 'Recibo: ' + datos[10]);

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
//#region

//#region FUNCIONES PARA FORMATO DE COMPROBANTE DE CERTIFICADO
// MAIN
function impresion_comprobante_certificado_main(datos) {
    var opciones = {
        orientation: 'P',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);
    var i = 1;
    var ini = 30;
    while (i < 4) {
        doc.text(10, ini, 'Certificado. ' + datos[2]);
        doc.text(97, ini, 'Fecha. ' + datos[3]);
        doc.text(145, ini, 'Codigo de cuenta' + datos[4]);

        ini = ini + 10;
        doc.text(10, ini, 'Cliente: ' + datos[5]);

        ini = ini + 10;
        doc.text(10, ini, 'Monto apertura: ' + datos[6]);
        doc.text(130, ini, 'Interes: Q ' + datos[7]);

        ini = ini + 12;
        doc.text(10, ini, 'ISR: ' + datos[8]);
        ini = ini + 10;
        doc.text(40, ini, 'Monto en letras: ' + datos[9]);
        ini = ini + 10;
        doc.text(10, ini, 'Recibo: ' + datos[10]);
        ini = ini + 60;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_certificado_mayaland(datos) {
    // console.log(datos);
    // return;
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(11);

    var x = 1;
    var y = 40;

    doc.text(x + 80, y, datos[0][2]); //no. de cuenta
    y = y + 10;
    doc.text(x + 10, y, datos[0][1]); //nombre
    y = y + 10;
    doc.text(x + 10, y, datos[0][4]); //dpi
    doc.text(x + 85, y, datos[0][21]); //muni extiende
    y = y + 10;
    doc.text(x + 30, y, datos[0][3]); //direccion exacta
    y = y + 10;
    doc.text(x + 30, y, datos[0][6] + '-EXACTOS'); //monto en letras
    y = y + 10;
    let montoFormateado = Number(datos[0][7]).toLocaleString('es');
    let monto = Number(datos[0][7]);
    doc.text(x + 108, y - 1, '****' + montoFormateado + '****'); //monto en numeros
    y = y + 10;
    doc.text(x + 100, y, datos[0][8] + " dias.");
    y = y + 10;
    doc.text(x + 55, y, datos[0][23]); //fecha de vencimiento
    y = y + 10;
    y = y + 10;
    let tasa = parseFloat(datos[0][11])
    let tasaFormateada = parseFloat(datos[0][11]).toFixed(2) + "%";
    doc.text(x + 3, y - 4, tasaFormateada); // tasa
    y = y + 10;
    y = y + 10;
    y = y + 10;
    y = y + 10;
    // // let muultitasa = monto * (tasa/100)
    // let interescalculado=datos[0][12];
    // let isr=datos[0][13];
    // let interesfinal=interescalculado-isr;
    // let resultadoFormateado = interesfinal.toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    // doc.text(x + 130, y, "Pago de Interes: ****" + resultadoFormateado+"****"); // tasa
    doc.text(x + 25, y, 'IXCAN, ' + datos[0][22]);
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

    //var i = 0;
    var i = posini;
    var tiptr;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var monto = parseFloat(0);

    while (i < (datos[1].length)) {
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        doc.text(22, pos, "" + fecha);
        doc.text(42, pos, "" + datos[1][i]['cnumdoc']);
        detalle = datos[1][i]['crazon'];
        doc.text(58, pos, "" + detalle);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];

        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            doc.text(103, pos, "");
            doc.text(103, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            doc.text(81, pos, "" + pad(currency(monto)));
            doc.text(81, pos, "");
        }

        doc.text(130, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
        pos += 5;
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
        doc.text(margenizquierdo, ini, 'Cuenta de Ahorro No. ' + datos[2]);
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

function impresion_recibo_dep_ret_adif(datos) {
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
        doc.text(margenizquierdo, ini, 'Cuenta de Ahorro No. ' + datos[2]);
        doc.text(margenizquierdo + 90, ini, 'Fecha doc: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo + 100, ini, 'Monto: Q ' + datos[3]);

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

function impresion_recibo_dep_ret_dynamics(datos) {
    // console.log(datos);
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
        doc.setFontSize(10);

        doc.text(30, ini, datos[14]);
        doc.text(47, ini, datos[13]);
        let valor = datos[12].toString();
        let ultimosDosDigitos = valor.substring(valor.length - 2);
        doc.text(68, ini, ultimosDosDigitos);


        ini = ini + 6;
        doc.text(margenizquierdo + 16, ini, datos[2]); //CUENTA

        ini = ini + 6;
        doc.text(margenizquierdo + 16, ini, datos[26]); //Direccion
        doc.text(margenizquierdo + 105, ini, datos[3]); //cantidad

        ini = ini + 6;
        doc.text(margenizquierdo + 20, ini, datos[11]); // Cantidad en letras

        ini = ini + 6;
        doc.text(margenizquierdo + 17, ini, datos[7]); //Cliente

        if (datos[28] == 'AHORRO CORRIENTE') {
            doc.setFontSize(8);
            doc.text(margenizquierdo + 94, 78.1, datos[3]);
            doc.text(margenizquierdo + 92, 106, "Q. " + datos[3]);
        }
        if (datos[28] == 'AHORRO A PLAZO') {
            doc.setFontSize(8);
            doc.text(margenizquierdo + 94, 81, datos[3]);
            doc.text(margenizquierdo + 92, 106, "Q. " + datos[3]);
        }

        if (datos[23] == 'D') {
            doc.setFontSize(8);
            let saldoActual1 = parseFloat(datos[24]);
            let deposito = parseFloat(datos[25]);
            let saldoAnterior1 = saldoActual1 - deposito;
            ini = ini + 13;
            doc.text(margenizquierdo + 117, ini, ' ' + saldoAnterior1);

            ini = ini + 28;
            doc.text(margenizquierdo + 117, ini, ' ' + datos[24]);
        }

        if (datos[23] == 'R') {
            let saldoActual = parseFloat(datos[24]);
            let retiro = parseFloat(datos[25]);
            let saldoAnterior = saldoActual + retiro;
            ini = ini + 6;
            doc.text(margenizquierdo, ini, 'Saldo Anterior: Q. ' + saldoAnterior);
            doc.text(margenizquierdo + 70, ini, 'Saldo Actual: Q. ' + datos[24]);
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

function impresion_libreta_dynamics(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    //    // console.log(datos);
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

    //var i = 0;
    var i = posini;
    var tiptr;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var monto = parseFloat(0);

    while (i < (datos[1].length)) {
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        doc.text(7, pos, "" + fecha);
        doc.text(30, pos, "" + datos[1][i]['cnumdoc']);
        detalle = datos[1][i]['crazon'];
        doc.text(55, pos, "" + detalle);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];

        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            doc.text(86, pos, "");
            doc.text(86, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            doc.text(113, pos, "" + pad(currency(monto)));
            doc.text(113, pos, "");
        }

        doc.text(141, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
        pos += 5;
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

function impresion_certificado_codepa(datos) {
    // // console.log(datos);
    // return;
    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    datosCuenta = datos[4];
    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(11);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datosCuenta['montoapr']).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datosCuenta['fecapr']);
    var fechafin = conviertefecha(datosCuenta['fec_ven']);

    const y = 38;
    const x = -10;
    //texto en negrita
    doc.setFont('helvetica', 'bold');
    //   doc.text( x+28, y+10, 'Nombre del Asociado:');
    //   doc.text( x+28, y+20, 'Cuenta No.');
    //   doc.text( x+19, y + 35, "DPI:");
    //   doc.text( x+95, y + 35, 'Teléfono:');

    //   doc.text( x+19, y+45, 'Dirección:');
    //   doc.text( x+20, y+60, 'Monto:');
    //   doc.text( x+20, y+70, 'Monto en letras:');

    //   doc.text( x+20, y+85, 'Plazo:');
    //   doc.text(x+95, y+85,'Interes:');
    //   doc.text( x+20, y+95, 'Fecha de Apertura:');
    //   doc.text(x+95, y+95, 'Fecha de Vencimiento:');

    //texto normal
    doc.setFont('helvetica', 'normal');
    doc.text(x + 40, y + 5, fechaini);//FECHA INICIO
    doc.text(x + 180, y + 5, datosCuenta['codaho']);
    doc.text(x + 45, y + 14, datosCuenta['nombre']);
    doc.text(x + 50, y + 23, datosCuenta['dire']); //Direccion
    doc.text(x + 70, y + 32, datosCuenta['montoletra']);// monto en letras
    doc.text(x + 200, y + 32, intmonto);//monto +15 de texto
    doc.text(x + 75, y + 41, datosCuenta['plazo'] + ' días.'); //PLAZO
    doc.text(x + 205, y + 41, fechafin);//FECHA vence
    doc.text(x + 175, y + 49, intere + '%');//interes
    //   doc.text( x+29, y+35, datosCuenta['dpi']); //Dpi
    //   doc.text( x+120,y+35, datosCuenta['tel']); //telefono

    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    const max_caracteres = 10;
    // console.log(datos[1]);
    for (let i = 1; i < datos[1].length; i++) {
        beneficiarios = datos[1][i]['nombre'];
        dpi = dpi + datos[1][i]['dpi'];
        decripc = datos[1][i]['codparent'];
        tel = tel + datos[1][i]['telefono'];
        doc.text(x + 60, y + 50 + (i * 7), beneficiarios + " (" + decripc + ")");
        if (i !== ((datos[1].length) - 1)) {
            beneficiarios = beneficiarios + ", ";
            dpi = dpi + ", ";
            decripc = decripc + ", ";
            tel = tel + ", ";
        }

    }
    //   var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    //   var dpi2 = doc.splitTextToSize(dpi, 180);
    //   var decripc2 = doc.splitTextToSize(decripc, 180);
    //   var tel2 = doc.splitTextToSize(tel, 180);

    console.log(datos[1].length)

    if (datos[1].length > 0) {

        //   doc.text(x+40,y+ 59, splitTitle + ", " + decripc2 + " "); //beneficiario
        //   doc.text(x+95,y+ 140, "(" + decripc2 + ")"); //parentezco
        //   doc.text(x+120,y+ 140, dpi2); //DPI beneficiario
        // doc.text(175,y+ 44, tel2); //telefono
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_libreta_codepa(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log("impresion libreta main")
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
        doc.text(8, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = datos[1][i]['cnumdoc'];
            doc.text(35, pos, transaccion);
            doc.text(64, pos, "");
            doc.text(90, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = datos[1][i]['cnumdoc'];

            doc.text(35, pos, transaccion);
            doc.text(68, pos, "" + pad(currency(monto)));
            doc.text(88, pos, "");
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

function impresion_recibo_dep_ret_copim(datos) {
    alert("Impresion de recibo");
    // console.log(datos);
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

        // concepto con salto de linea
        var maxWidth = 120;
        var lineas = doc.splitTextToSize("CONCEPTO: " + datos[29], maxWidth);
        ini = ini + 7;
        lineas.forEach(function (linea, index) {
            doc.text(margenizquierdo, ini + (index * 5), linea);
        });
        i++;
        if (datos[23] == 'D') {
            doc.text(margenizquierdo + 55, 54, datos[7]);//nombre
            doc.text(170, ini + 13, datos[3]);//saldo
            ini = ini + 23;
            doc.text(margenizquierdo + 25, ini, datos[11]); // Cantidad en letras
        }
        if (datos[23] == 'R') {
            doc.text(180, ini + 6, "Q. " + datos[3]);//saldo
            ini = ini + 23;
            doc.text(margenizquierdo + 33, ini - 11, datos[11]); // Cantidad en letras
        }
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_libreta_copim(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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

function impresion_certificado_copim(datos) {
    //// console.log(datos);
    // return;
    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    datosCuenta = datos[4];

    doc.setFontSize(9);
    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datosCuenta['montoapr']).toLocaleString('en-US');
    var intere = parseFloat(datosCuenta['interes']);
    var fechaini = datosCuenta['fecha_apertura_letra'];
    var fechafin = conviertefecha(datosCuenta['fec_ven']);

    const x = 40;
    const y = 22;
    //texto en negrita
    doc.setFont('helvetica', 'bold');

    doc.setFont('helvetica', 'normal');
    var agencia = '';
    if(datosCuenta['id_agencia']=='1'){
        doc.text(x + 5, y + 12, datosCuenta['codaho']); //cuenta
        doc.text(x + 90, y + 12, datosCuenta['dpi']); //Dpi
        
        doc.text(x + 10, y + 19, datosCuenta['nombre']); //nombre del cliente
        doc.text(x + 167, y + 19, intere + '%');//interes
        
        doc.text(x + 15, y + 25, datosCuenta['dire']); //Direccion
        doc.text(x + 145, y + 25, datosCuenta['tel']); //telefono
        
        doc.text(x + 5, y + 34, datosCuenta['montoletra']);// monto en letras
        doc.text(x + 90, y + 34, intmonto);//monto
        doc.text(x + 155, y + 34, datosCuenta['plazo'] + ' días.'); //PLAZO
        
        doc.text(x + 5, y + 40, fechaini);//FECHA DEPOSITO
        doc.text(x + 170, y + 40, fechafin);//FECHA vence
        agencia = "Cobán, Alta Verapaz ";
        doc.text(x, y + 79, agencia + datosCuenta['fecha_apertura_letra']); //Fecha de impresion
    } else if(datosCuenta['id_agencia']=='2'){
        doc.text(x + 5, y + 11, datosCuenta['codaho']); //cuenta
        doc.text(x + 90, y + 11, datosCuenta['dpi']); //Dpi

        doc.text(x + 10, y + 18, datosCuenta['nombre']); //nombre del cliente
        doc.text(x + 167, y + 18, intere + '%');//interes

        doc.text(x + 15, y + 24, datosCuenta['dire']); //Direccion
        doc.text(x + 145, y + 24, datosCuenta['tel']); //telefono

        doc.text(x + 5, y + 32, datosCuenta['montoletra']);// monto en letras
        doc.text(x + 90, y + 32, intmonto);//monto
        doc.text(x + 155, y + 32, datosCuenta['plazo'] + ' días.'); //PLAZO

        doc.text(x + 5, y + 39.5, fechaini);//FECHA DEPOSITO
        doc.text(x + 170, y + 39.5, fechafin);//FECHA vence
        agencia = "Playa Grande, Ixcan ";
        doc.text(x, y + 79, agencia + datosCuenta['fecha_apertura_letra']); //Fecha de impresion
    }

    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    const max_caracteres = 80;
    // console.log(datos[1]);
    // Procesar beneficiarios y campos relacionados
    let beneficiariosArr = [];
    let dpiArr = [];
    let direcArr = [];
    let porcenArr = [];
    for (let i = 1; i < datos[1].length; i++) {
        beneficiariosArr.push(datos[1][i]['nombre'] || '');
        dpiArr.push(datos[1][i]['dpi'] || '');
        direcArr.push(datos[1][i]['direccion'] || '');
        porcenArr.push(datos[1][i]['porcentaje'] || '');
    }
    const ln = 7;

    if (datos[1].length > 0) {
        let baseY = y + 53;
        for (let i = 0; i < beneficiariosArr.length; i++) {
            doc.text(x - 25, baseY + i * ln, beneficiariosArr[i]);
            doc.text(x + 55, baseY + i * ln, direcArr[i]);
            doc.text(x + 125, baseY + i * ln, dpiArr[i]);
            doc.text(x + 172, baseY + i * ln, porcenArr[i] + '%');
        }
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_codepa(datos) {
    alert("Impresion de recibo");
    // console.log(datos);
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

        // doc.text(margenizquierdo + 83, ini - 4, datos[14]);//dia
        // doc.text(margenizquierdo + 100, ini - 4, datos[13]);//mes
        // doc.text(margenizquierdo + 115, ini - 4, datos[12]);//año
        // doc.text(margenizquierdo + 130, ini, 'Fecha operación: ' + datos[10]);
        ini = ini + 5;
        doc.text(margenizquierdo + 20, ini, datos[7]);//nombre
        doc.text(margenizquierdo + 130, ini, datos[4]);//fecha doc
        ini = ini + 7;
        doc.text(margenizquierdo + 22, ini, datos[26]); //Direccion
        doc.text(margenizquierdo + 140, ini, datos[2]); //Cuenta
        ini = ini + 20;
        doc.text(margenizquierdo, ini, datos[16]); //Operacion
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Fecha doc: ' + datos[4]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Codigo de Cliente: ' + datos[21]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Operacion y No. Docto: ' + datos[5]);
        ini = ini + 5;
        // // doc.text(margenizquierdo, ini, 'Cantidad en Letras:'); // Cantidad en letras
        // // ini = ini + 5;
        // doc.text(margenizquierdo, ini,datos[11]);
        doc.text(margenizquierdo + 165, ini + 13, "Q. " + datos[3]);//saldo

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
function impresion_libreta_copim(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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
function impresion_libreta_coperural(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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
    doc.setFont('Arial', 'bold');
    doc.setFontSize(8);

    // Margen izquierdo
    var margenIzquierdo = -5;

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
        pos = (7.3 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(margenIzquierdo + 17, pos, "" + fecha);
        doc.text(margenIzquierdo + 38, pos, " " + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        console.log("TIPO DE TRANSACCION: " + tiptr);
        console.log(datos[1][i]['ctipdoc']);
        if (tiptr == "D" && datos[1][i]['ctipdoc'] != "IN") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "D/";
            doc.text(margenIzquierdo + 35, pos, transaccion);
            doc.text(margenIzquierdo + 54, pos, "");
            doc.text(margenIzquierdo + 53, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D" && datos[1][i]['ctipdoc'] == "IN") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "D/.";
            doc.text(margenIzquierdo + 35, pos, transaccion);
            doc.text(margenIzquierdo + 54, pos, "");
            doc.text(margenIzquierdo + 74, pos, "" + pad(currency(monto)));
        }

        if (tiptr == "R" && datos[1][i]['ctipdoc'] !== "IP") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "R/";
            doc.text(margenIzquierdo + 35, pos, transaccion);
            doc.text(margenIzquierdo + 95, pos, "" + pad(currency(monto)));
            doc.text(margenIzquierdo + 78, pos, "");
        }
        if (tiptr == "R" && datos[1][i]['ctipdoc'] == "IP") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "R/.";
            doc.text(margenIzquierdo + 35, pos, transaccion);
            doc.text(margenIzquierdo + 95, pos, "" + pad(currency(monto)));
            doc.text(margenIzquierdo + 78, pos, "");
        }

        doc.text(margenIzquierdo + 114, pos, "" + pad(currency(datos[1][i]['saldo'])));
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



function impresion_recibo_dep_ret_coperural(datos) {
    // console.log(datos);
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

        if (datos[23] == 'D') {

            doc.setFontSize(10);
            doc.text(margenizquierdo + 8, ini, datos[7]); //Nombre del cliente
            // doc.text(margenizquierdo, ini, 'Cuenta: ' + datos[2]);
            doc.text(margenizquierdo + 138, ini, datos[15]); //Numero de asociado
            // doc.text(180, ini, '' + datos[5]);

            ini = ini + 6;
            doc.text(margenizquierdo + 8, ini, datos[26]); //direccion
            //doc.text(margenizquierdo + 130, ini,  datos[4]); //fecha
            ini = ini + 4;
            doc.text(margenizquierdo + 138, ini - 4, datos[14]);//dia
            doc.text(margenizquierdo + 147, ini - 4, datos[13]);//mes
            doc.text(margenizquierdo + 155, ini - 4, datos[12]);//año


            doc.text(margenizquierdo + 100, 85, datos[11]); // Cantidad en letras
            // doc.text(margenizquierdo + 16,ini,datos[26]); //Direccion


            //doc.text(margenizquierdo + 105,60,datos[3]); //cantidad

            if (datos[28] == 'Ahorro corriente') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 40, 92, datos[3]);
                doc.text(margenizquierdo + 40, 110.1, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro a plazo fijo') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 40, 98, datos[3]);
                doc.text(margenizquierdo + 40, 110.1, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro infanto juvenil') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 40, 102, datos[3]);
                doc.text(margenizquierdo + 40, 110.1, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro programado') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 40, 106, datos[3]);
                doc.text(margenizquierdo + 40, 110.1, datos[3]); // TOTAL
            }


            doc.setFontSize(10);
            let saldoActual1 = parseFloat(datos[24]); // SALDO ACTUAL
            let deposito = parseFloat(datos[25]); // SALDO ANTERIOR
            let saldoAnterior1 = saldoActual1 - deposito;
            doc.text(119, 68, ' ' + saldoAnterior1); //SALDO ANTERIOR
            doc.text(119, 73, ' ' + datos[3]); //ABONO
            doc.text(119, 78, ' ' + datos[24]); //SALDO ACTUAL
        }

        if (datos[23] == 'R') {

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


            // doc.text(30, 55, ' ' + datos[3]); //CANTIDAD EN NUMERO



            //doc.text(margenizquierdo + 105,60,datos[3]); //cantidad

            if (datos[28] == 'Ahorro corriente') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 75, 67, datos[3]);
                doc.text(margenizquierdo + 75, 86, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro a plazo fijo') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 75, 71, datos[3]);
                doc.text(margenizquierdo + 75, 86, datos[3]); // TOTAL
            }


            // let saldoActual = parseFloat(datos[24]);
            // let retiro = parseFloat(datos[25]);
            // let saldoAnterior = saldoActual + retiro;
            // ini = ini + 6;
            // doc.text(35, 105, ' ' + saldoAnterior); //SALDO ANTERIOR
            // doc.text(35, 111, ' ' + datos[3]); //ABONO
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

function impresion_certificado_coperural(datos) {
    // console.log(datos);
    // return;
    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    datosCuenta = datos[4];

    doc.setFontSize(9);
    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datosCuenta['montoapr']).toLocaleString('en-US');
    var intere = parseFloat(datosCuenta['interes']);
    var fechaini = datosCuenta['fechaletra'];
    var fechafin = conviertefecha(datosCuenta['fec_ven']);

    const x = 40;
    const y = 66;
    //texto en negrita
    doc.setFont('helvetica', 'bold');

    doc.setFont('helvetica', 'normal');
    doc.text(x + 5, y + 15, datosCuenta['nombre']); //nombre
    doc.text(x + 131, y + 15, datosCuenta['codaho']); //cuenta

    doc.text(x + 10, y + 19, datosCuenta['dire']); //Direccion
    doc.text(x + 105, y + 37, intere + '%');//interes

    doc.text(x + 10, y + 24, datosCuenta['dpi']); //dpi
    doc.text(x + 105, y + 24, datosCuenta['tel']); //telefono

    doc.text(x + 20, y + 28, datosCuenta['montoletra']);// monto en letras
    doc.text(x + 10, y + 32, intmonto);//monto
    doc.text(x + 45, y + 32, datosCuenta['plazo'] + ' días.'); //PLAZO

    doc.text(x + 128, y + 32, fechaini);//FECHA INICIO
    doc.text(x + 45, y + 36.5, fechafin);//FECHA vence
    doc.text(x + 20, y + 173, "Coban, Alta Verapaz, " + datosCuenta['fechaletra']); //Fecha de impresion

    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let decripc2 = ""; // 🔹 Nueva variable agregada
    let tel = "";
    const max_caracteres = 80;
    // console.log(datos[1]);
    // Procesar beneficiarios y campos relacionados
    let beneficiariosArr = [];
    let dpiArr = [];
    let direcArr = [];
    let porcenArr = [];
    let decripcArr = []; // 🔹 Array para decripc2

    for (let i = 1; i < datos[1].length; i++) {
        beneficiariosArr.push(datos[1][i]['nombre'] || '');
        dpiArr.push(datos[1][i]['dpi'] || '');
        direcArr.push(datos[1][i]['direccion'] || '');
        porcenArr.push(datos[1][i]['porcentaje'] || '');
        decripcArr.push(datos[1][i]['codparent'] || ''); // 🔹 llenamos el array
    }
    const ln = 7;

    if (datos[1].length > 0) {
        let baseY = y + 56;
        for (let i = 0; i < beneficiariosArr.length; i++) {
            doc.text(x + 20, y + 49, beneficiariosArr[i]);
            doc.text(x + 15, y + 54, dpiArr[i]);
            doc.text(x + 14, baseY + 2.3, direcArr[i]);
            doc.text(x + 16, baseY + 6, decripcArr[i]); // 🔹 aquí imprimimos decripc2
        }
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'));

}

function impresion_recibo_dep_ret_kotan(datos) {
    // console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 35;
    var rec2 = 110;
    var margenizquierdo = 30;
    while (i < 2) {
        doc.setFontSize(8);

        // doc.text(margenizquierdo, ini, 'CAMPAÑIA INVERIONISTA KOTANH');



        ini = ini + 6;

        doc.text(margenizquierdo, ini, 'Producto: ' + datos[16]);
        doc.text(margenizquierdo + rec2, ini, 'Producto: ' + datos[16]);

        ini = ini + 6;
        if (datos[23] == 'D') {
            doc.text(margenizquierdo, ini, 'Tipo de Operacion: DEPOSITO');
            doc.text(margenizquierdo + rec2, ini, 'Tipo de Operacion: DEPOSITO');
        } else if (datos[23] == 'R') {
            doc.text(margenizquierdo, ini, 'Tipo de Operacion: RETIRO');
            doc.text(margenizquierdo + rec2, ini, 'Tipo de Operacion: RETIRO');
        }

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Cuenta: ' + datos[2]);
        doc.text(margenizquierdo + rec2, ini, 'Cuenta: ' + datos[2]);
        // doc.text(margenizquierdo + 80, ini, 'Fecha: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        doc.text(margenizquierdo + rec2, ini, 'Cliente: ' + datos[7]);

        ini = ini + 6;
        // doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo, ini, 'Cantidad: Q ' + datos[3] + ' N. Docto: ' + datos[5]);
        doc.text(margenizquierdo + rec2, ini, 'Cantidad: Q ' + datos[3] + ' N. Docto: ' + datos[5]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Fecha Operación: ' + datos[4]);
        doc.text(margenizquierdo + rec2, ini, 'Fecha Operación: ' + datos[4]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8]);
        doc.text(margenizquierdo + rec2, ini, 'Operador: ' + datos[8]);

        // Espacio para firmas
        // ini = ini + 15;

        // Líneas para firmas
        // doc.line(margenizquierdo, ini, margenizquierdo + 30, ini); // Primera línea de firma
        // doc.line(margenizquierdo + 40, ini, margenizquierdo + 70, ini); // Segunda línea de firma

        // Etiquetas de firmas
        // ini = ini + 4;
        // doc.text(margenizquierdo + 10, ini, 'ABONO');
        // doc.text(margenizquierdo + 38, ini, 'ROBERTO JUAN BALTAZAR');

        // ini = ini + 4;
        // doc.text(margenizquierdo + 42, ini, 'GERENTE GENERAL');

        ini = ini + 6;
        doc.text(margenizquierdo, ini, datos[6]); //concepto
        doc.text(margenizquierdo + rec2, ini, datos[6]); //concepto


        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
function impresion_recibo_dep_ret_emprendedor(datos) {
    console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 40;
    var margenizquierdo = 40;
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
        doc.text(margenizquierdo, ini, 'Fecha:'); doc.text(margenizquierdo + 40, ini, datos[4]);
        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Nombre:'); doc.text(margenizquierdo + 40, ini, datos[7]);
        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Código:') ; doc.text(margenizquierdo + 40, ini, datos[21]);
        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'No. de Cuenta:');   doc.text(margenizquierdo + 40, ini, datos[2]);  doc.text(margenizquierdo + 85, ini, datos[16]);
        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Tipo de Transacción:');     doc.text(margenizquierdo + 40, ini, transaccion);

        ini = ini + 8;
        doc.text(margenizquierdo, ini, 'Monto:');   doc.text(margenizquierdo + 40, ini, 'Q ' + datos[3]);
        ini = ini + 4;
        doc.text(margenizquierdo+ 6, ini, 'TOTAL:');   doc.text(margenizquierdo + 40, ini, 'Q ' + datos[3]);  doc.text(margenizquierdo + 70, ini, 'Saldo Actual:');   doc.text(margenizquierdo +  100, ini, 'Q ' + datos[24]);

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
    doc.setFontSize(9);
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
        doc.text(10, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        docto = datos[1][i]['cnumdoc'];
        ctipdoc = datos[1][i]['ctipdoc'];
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (ctipdoc == "IN" || ctipdoc == "IP") {

            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "IN - IP/" + " " + docto;
            doc.text(35, pos, transaccion);
            doc.text(63, pos, "" + pad(currency(monto)));
            doc.text(90, pos, "");
        } else {
            if (tiptr == "D" || tiptr == "R") {
                saldo = parseFloat(saldo) + parseFloat(monto);
                transaccion = tiptr + "/" + " " + docto;
                doc.text(35, pos, transaccion);
                doc.text(63, pos, "");
                doc.text(90, pos, "" + pad(currency(monto)));
            }
        }

        doc.text(117, pos, "" + pad(currency(datos[1][i]['saldo'])));
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


// IMPRESION DE CERTIFICADO DE AHORRO de emprendedor
function impresion_certificado_emprendedor(datos) {
    // console.log(datos);
    // return;
    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(13);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);

    const y = 70;
    const x = 4;
    //texto en negrita
    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');
    doc.text(x + 30, y + 12, 'Nombre: ' + datos[0][1]);
    doc.text(x + 120, y + 12, 'Cuenta No. ' + datos[0][2]);
    doc.text(x + 100, y + 16, "DPI: " + datos[0][4]);
    doc.text(x + 140, y + 16, 'Tel: ' + datos[0][5]);

    doc.text(x + 30, y + 16, 'Dirección: ' + datos[0][3]);
    doc.text(x + 145, y + 20, 'Monto: ' + 'Q.' + intmonto);
    doc.text(x + 30, y + 20, 'Monto en letras: ' + datos[0][6]);

    doc.text(x + 30, y + 24, 'A Plazo ' + datos[0][8] + ' días.');
    doc.text(x + 110, y + 28, 'Interes: ' + intere + '%' + ' Anual');
    doc.text(x + 60, y + 24, 'Fecha de Apertura: ' + fechaini);
    doc.text(x + 30, y + 28, 'Fecha de Vencimiento: ' + fechafin);

    // Definir tu párrafo

    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');
    let parrafo1 = 'La Cooperativa Integtral de Ahorro y Credito "Los Emprendedores" Responsabilidad Limitada, CERTIFICA: Que ha recibido el ahorro a plazo fijo y entregara al asiociado a partir del día hábil siguiente a la fecha de su vencimiento, la cantidad depositada y los interes devengados.';
    let parrafo2 = 'CONDICIONES: 1- La Coopeerativa aceptará el deposito bajo las condiciones establecidas y descritas en el presente -2062 5 días despúes de la fecha de vencimiento, de lo contrario se renovará automáticamente el contrato por el mismo periodo anterior, aplicándose la tasa de interés vigentes a la fecha de prorroga, capitalizandole los intereses devengados a la fecha de vencimiento. 4- Los certificados que sean cancelados, deberán ser devueltos al vencimiento del plazo pactado, contra el cual se le entragara el saldo de capital e intereses correspondientes a que tenga derecho. 5- Los certificados que autorice y entregue la cooperativa para comprobar el saldo de los depósitos a plazo, constituirán titulos ejecutivos para exigir judicialmente el capital y los respectivos intereses que tales documentos expresen, a favor del legitimo titular o beneficiario asignado en caso de muerte previa comprobación. 6- En caso de extravío del certificado de deposito, debera hacerse por escrito, para cancelar la copia en poder de la Cooperativa y luego emitir un nuevo certificado de los cuales deberan pagar una cuota de Q.50.00 por gastos administrativos. 7- Solamente el titular de la cuenta, podra efectuar retiros de los fondos, firmando los comprobantes de liquidación. 8- Cuando una persona pretenda efectuar retiros de los fondos con un documento del que no sea titular, sin tener la autorización respectiva, para ello, se procederá a decomisar el documento, sin perjuicio de las responsabilidades legales. 9- La tasa de interés pactada en un certificado de deposito no podra variar durante el plazo convenido a efecto de garantizar una rentabilidad estable a favor del titular de la cuenta. 10- Los casos no previstos en las condiciones anteriores, los resolvera el Consejo de Administración de la Cooperativa.';
    // Ancho
    let anchoTexto = 155;
    let linea1 = doc.splitTextToSize(parrafo1, anchoTexto);
    let linea2 = doc.splitTextToSize(parrafo2, anchoTexto);

    doc.text(linea1, x + 30, y - 1, { align: "justify" });
    doc.text(linea2, x + 30, y + 62, { align: "justify" });

    doc.text(x + 30, y + 140, "Lugar y fecha:  Canton Xolacul Nebaj " + datos[0][15]); //Fecha de impresion
    doc.text(x + 30, y + 150, "F.____________________________");
    doc.text(x + 40, y + 154, "Firma del Asociado");
    doc.text(x + 120, y + 154, "Representante Legal de la Cooperativa");
    doc.text(x + 120, y + 150, "F._____________________________");

    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    let direccion = "";
    const max_caracteres = 10;
    // console.log(datos[1]);
    for (let i = 1; i < datos[1].length; i++) {
        beneficiarios = beneficiarios + datos[1][i]['nombre'];
        dpi = dpi + datos[1][i]['dpi'];
        decripc = decripc + datos[1][i]['codparent'];
        tel = tel + datos[1][i]['telefono'];
        direccion = direccion + datos[1][i]['direccion'];
        if (i !== ((datos[1].length) - 1)) {
            beneficiarios = beneficiarios + "; ";
            dpi = dpi + "; ";
            decripc = decripc + "; ";
            tel = tel + "; ";
            direccion = direccion + "; ";
        }

    }
    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    var dpi2 = doc.splitTextToSize(dpi, 180);
    var decripc2 = doc.splitTextToSize(decripc, 180);
    var tel2 = doc.splitTextToSize(tel, 180);

    console.log(datos[1].length)

    if (datos[1].length > 0) {
        doc.setFontSize(9);
        doc.setFont('helvetica', 'normal');
        doc.text(x + 30, y + 36, 'BENEFICIARIOS(A):');
        doc.text(x + 30, y + 40, 'Nombre (s):  ' + splitTitle);
        doc.text(x + 30, y + 52, 'Parentesco:  ' + decripc2);
        doc.text(x + 30, y + 44, 'No. DPI:  ' + dpi2);
        doc.text(x + 30, y + 48, 'Dirección:  ' + direccion);
        // doc.text(175,y+ 40, 'NO. TELEFONO');
        doc.setFont('helvetica', 'normal');

    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
function impresion_recibo_dep_ret_reinita(datos) {
    console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 50;
    var margenizquierdo = 40;
    while (i < 2) {
        doc.setFontSize(10);
        doc.text(margenizquierdo, ini, 'Cuenta: ' + datos[2]);
        doc.text(margenizquierdo + 80, ini, 'Fecha: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo + 80, ini, 'Monto: Q ' + datos[3]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operación: ' + datos[6]);
        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Producto: ' + datos[16]);

        ini = ini + 6;
        // doc.text(margenizquierdo, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);
        // ini = ini + 5;
        doc.text(margenizquierdo, ini, datos[10]);

        ini = ini + 15;

        // Medir ancho de los nombres
        let fcliente = 'F. ' + datos[7].toUpperCase();
        let fope = 'F. ' + datos[8].toUpperCase() + ' ' + datos[9].toUpperCase();
        let ancho1 = doc.getTextWidth(fcliente);
        let ancho2 = doc.getTextWidth(fope);

        // Calcular posiciones centradas
        let centro1 = 20 + ancho1 / 2;
        let centro2 = (margenizquierdo + 80) + ancho2 / 2;
        // líneas centradas respecto al texto
        let largoLinea = 50;
        doc.line(20, ini, 20 + largoLinea, ini);
        doc.line(margenizquierdo + 80, ini, margenizquierdo + 80 + largoLinea, ini);

        ini = ini + 4;
        doc.text(20 + (largoLinea / 2) - (ancho1 / 2), ini, fcliente);
        doc.text(margenizquierdo + 80 + (largoLinea / 2) - (ancho2 / 2), ini, fope);

        // Líneas para firmas
        // doc.line(20, ini, margenizquierdo+35, ini); // Primera línea de firma
        // doc.line(margenizquierdo + 90, ini, margenizquierdo + 140, ini); // Segunda línea de firma

        // // Etiquetas de firmas
        // ini = ini + 4;
        // doc.text(20, ini, 'F.' + ' ' + datos[7]);
        // doc.text(margenizquierdo + 80, ini, 'F. '+ ' ' + datos[8]);

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
function impresion_libreta_reinita(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log("impresion libreta main")
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
    //var i = 0;
    var i = posini;
    var tiptr;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var cnumdoc
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        // console.log(datos[1][i]);
        doc.setFontSize(9);
        cnumdoc = datos[1][i]['cnumdoc'];
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(5, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = cnumdoc; // retiro/debito<
            doc.text(26, pos, transaccion);
            doc.text(50, pos, "" + pad(currency(monto)));
            doc.text(88, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = cnumdoc; // deposito/credito

            doc.text(26, pos, transaccion);
            doc.text(64, pos, "");
            doc.text(78, pos, "" + pad(currency(monto)));
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


function impresion_certificado_ciacri(datos) {
    // console.log(datos);
    // return;
    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    //console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(13);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);
    const fecha = datos[0][15];
    const [anio, mes, dia] = fecha.split("-")
    mesp = convertir_mes(mes)


    const y = 93;
    const x = 68;
    const y2 = 13;
    //texto en negrita
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.text(x, y, dia + "                                           " + mesp + "                                              " + anio);
    doc.text(x - 12, y + y2, datos[0][2]);
    doc.text(x, y + y2 * 2, datos[0][1]);
    doc.text(x + 94, y + y2, intmonto);
    doc.text(x, y + y2 * 3, datos[0][6]);

    doc.text(x + 85, y + y2 * 5, intere + '%');
    doc.text(x + 50, y + y2 * 4, fechaini);
    doc.text(x, y + y2 * 5, fechafin);

    //texto normal
    doc.setFont('helvetica', 'normal');
    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    const max_caracteres = 10;
    // console.log(datos[1]);
    for (let i = 1; i < datos[1].length; i++) {
        beneficiarios = beneficiarios + datos[1][i]['nombre'];
        dpi = dpi + datos[1][i]['dpi'];
        decripc = decripc + datos[1][i]['codparent'];
        tel = tel + datos[1][i]['telefono'];
        if (i !== ((datos[1].length) - 1)) {
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

    // console.log(datos[1].length)

    if (datos[1].length > 0) {
        doc.setFontSize(10);
        doc.setFont('helvetica', 'normal');
        //doc.text(x + 20, y + 130, 'Nombre Completo');
        //doc.text(x + 95, y + 130, 'Parentesco');
        //doc.text(x + 130, y + 130, 'DPI');
        // doc.text(175,y+ 40, 'NO. TELEFONO');
        for (let i = 0; i < splitTitle.length; i++) {
            doc.text(x - 20, y + 114 + (i * 6), splitTitle[i]); //beneficiario
            // doc.text(x + 95, y + 140 + (i * 6), decripc2[i]); //parentezco
        }
        //beneficiario
        //doc.text(x + 95, y + 140, decripc2); //parentezco
        //doc.text(x + 120, y + 140, dpi2); //DPI beneficiario
        // doc.text(175,y+ 44, tel2); //telefono
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}


function impresion_liquidacion_certificado_otziles(datos) {
    // console.log(datos);

    alert('Insertar hoja de certificado')
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 30;
    var ini2 = 3.5;
    var margenizquierdo = 55;
    doc.setFont("courier", "bold");
    const fecha = datos[3];
    const [anio, mes, dia] = fecha.split("-")
    totalint = parseFloat(datos[7]) - parseFloat(datos[8]);

    datosCuenta = datos[4];

    while (i < 2) {
        doc.setFontSize(11);
        doc.text(margenizquierdo, ini, 'ENTREGA AHORRO A PLAZO FIJO');
        doc.text(margenizquierdo, ini + ini2, 'Fecha: ' + dia + '-' + mes + '-' + anio + ', ' + 'Recibo: Nú' + datos[10]);
        doc.text(margenizquierdo, ini + ini2 * 2, 'D.P.I.: ' + datos[11] + ', Operador: ' + datos[16]);
        doc.text(margenizquierdo, ini + ini2 * 3, 'Asociado: ' + datos[5]);
        doc.text(margenizquierdo, ini + ini2 * 4, 'Plazo Nú.: ' + datos[2] + ', Aso. Nu: ' + datos[14]);
        doc.text(margenizquierdo, ini + ini2 * 5, 'Inicio: ' + datos[12] + ', Dias pagados: ' + datos[15] + ', Age.: 1');
        doc.text(margenizquierdo, ini + ini2 * 6, 'Ahorro Q.:' + parseFloat(datos[6]).toFixed(2) + ', Interes Q.:' + parseFloat(datos[7]).toFixed(2) + ', I.S.R. Q.: ' + parseFloat(datos[8]).toFixed(2));
        doc.text(margenizquierdo, ini + ini2 * 7, 'Finaliza: ' + datos[13]);
        doc.text(margenizquierdo, ini + ini2 * 8, 'Valor Q.:' + parseFloat(datos[6]).toFixed(2) + ' Tasa Int. Anual: ' + parseFloat(datos[17]).toFixed(2) + '%');
        doc.text(margenizquierdo, ini + ini2 * 9, 'Total a Recibir Q.:' + parseFloat(totalint).toFixed(2));


        // doc.text(10, ini, 'Certificado. ' + datos[2]);
        // doc.text(97, ini, 'Fecha. ' + datos[3]);
        // doc.text(145, ini, 'Codigo de cuenta: ' + datos[4]);

        // ini = ini + 10;
        // doc.text(10, ini, 'Cliente: ' + datos[5]);

        // ini = ini + 10;
        // doc.text(10, ini, 'Monto apertura: ' + datos[6]);
        // doc.text(130, ini, 'Interes: Q. ' + parseFloat(datos[7]).toFixed(2));

        // ini = ini + 12;
        // doc.text(10, ini, 'ISR: ' + parseFloat(datos[8]).toFixed(2));
        // ini = ini + 10;
        // doc.text(10, ini, 'Monto en letras: ' + datos[9]);
        // ini = ini + 10;
        // ini = ini + 60;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_libreta_kotan(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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
        pos = (5 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(19, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        ctipdoc = datos[1][i]['ctipdoc'];
        //*** */
        cont = 0;
        if (ctipdoc == "IN" || ctipdoc == "IP") {
            cont = i + 1;
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "IN - IP";
            doc.text(42, pos, String(cont));
            doc.text(48, pos, transaccion);
            doc.text(66, pos, "");
            doc.text(87, pos, "" + pad(currency(monto)));
        } else {
            if (tiptr == "D") {
                cont = i + 1;
                saldo = parseFloat(saldo) + parseFloat(monto);
                transaccion = "DEP";
                doc.text(42, pos, String(cont));
                doc.text(48, pos, transaccion);
                doc.text(66, pos, "");
                doc.text(87, pos, "" + pad(currency(monto)));
            }
            if (tiptr == "R") {
                cont = i + 1;
                saldo = parseFloat(saldo) - parseFloat(monto);
                transaccion = "RET";
                doc.text(42, pos, String(cont));
                doc.text(48, pos, transaccion);
                doc.text(66, pos, "" + pad(currency(monto)));
                doc.text(86, pos, "");
            }
        }
        doc.text(108.5, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            // console.log("aki papu");
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        cont++;
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

function impresion_certificado_kotan(datos) {
    // console.log(datos);
    // return;
    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);

    const y = 30;
    const x = 15;
    //texto en negrita
    // doc.setFont('helvetica', 'bold');
    // doc.text(x + 28, y + 10, 'Nombre del Asociado:');
    // doc.text(x + 28, y + 20, 'Cuenta No.');
    // doc.text(x + 19, y + 35, "DPI:");
    // doc.text(x + 95, y + 35, 'Teléfono:');

    // doc.text(x + 19, y + 45, 'Dirección:');
    // doc.text(x + 20, y + 60, 'Monto:');
    // doc.text(x + 20, y + 70, 'Monto en letras:');

    // doc.text(x + 20, y + 85, 'Plazo:');
    // doc.text(x + 95, y + 85, 'Interes:');
    // doc.text(x + 20, y + 95, 'Fecha de Apertura:');
    // doc.text(x + 95, y + 95, 'Fecha de Vencimiento:');

    //LOGO
    var img = new Image();
    img.src = '../' + datos[4].log_img;

    img.onload = function () {
        var logoX = 20;
        var logoY = 3;
        doc.addImage(img, 'PNG', logoX, logoY, 50, 30); // Ajusta ancho y alto
        //texto normal
        doc.setFont('times', 'bold');
        doc.setFontSize(20);
        doc.text(x + 28, y + 1, 'COMPAÑÍA INVERSIONISTA KOTANH');
        doc.setFontSize(15);
        doc.text(x + 46, y + 8, '   CERTIFICADO DE INVERSION');
        doc.setFontSize(13);
        doc.text(x + 75, y + 16, 'Número: ' + datos[0][0]); //No. certificado
        doc.setFont('times', 'normal');
        doc.setFontSize(11);
        doc.text(x + 28, y + 21, 'Asesoria Financiera Y Crediticia Para Micro Empresas Y Pequeñas Empresas');
        doc.text(x + 28, y + 26, '       Ticolal Pueblo Nuevo Jucup, San Sebastián Coatán, Huehuetenango');

        doc.text(x + 110, y + 36, "  Numero de Inversión: " + datos[0][2]); //cuenta
        doc.text(x + 110, y + 41, "Fecha de Inversion: " + fechaini);//FECHA INICIO

        doc.setFontSize(12);
        var nombremayus = String(datos[0][1]).toUpperCase()
        let parrafo1 = "COMPAÑIA INVERSIONISTA KOTANH, CERTIFICA que: Dentro del mecanismo de\nMercado Abierto, se ha efectuado una inversión en valores que pertenecen en custodia en la\nmisma compañia, la cual se encuentra representada por este certificado, en el cual se\nle garantiza la inversión realizada por " + nombremayus + ", Mediante el cual se otorga el derecho de percibir, apartir del día hábil siguiente a la fecha de vencimiento (fecha de recompra), el capital (valor de la inversión) y los intereses devengados que abajo se consignan, en la forma que se ha establecido en el contrato respectivo. Este derecho se ejercera mediante la presentacion y entrega del presente certificado en las oficinas centrales o agencias de la COMPAÑIA INVERSIONISTA KOTANH.";


        let parrafo2 = "Las condiciones descritas anteriormente pueden variar sin previo aviso al Inversionista, cuando lo\ndetermine GRUPO KOTANH S.A. por lo que el inversionista declara y acepta las disposiciones\ninternas del GRUPO KOTANH S.A. y el reglamente que regula la presente operación, los anexos y\ndocumentos legales de fromalizació que rigen esta inversión y servicios adicionales, son de su\ncomocimiento. No obstante, alguna duda al respecto, en cualquier momento usted puede consultarlos\nen las oficinas de GRUPO KOTANH S.A. todos los documentos relacionados a la apertura teniendo\ntambien a su disposición nuestro numero de telefono 53299417, donde podremos aclarar todas sus\ndudas."
        let anchoTexto = 172;
        let linea1 = doc.splitTextToSize(parrafo1, anchoTexto);
        let linea2 = doc.splitTextToSize(parrafo2, anchoTexto);

        doc.text(linea1, x + 15, y + 50, { align: "justify" });

        doc.setFontSize(11);
        doc.text(linea2, x + 15, y + 198, { align: "justify" });

        doc.setFontSize(12);
        doc.setFont('times', 'bold');
        doc.text(x + 15, y + 102, "Valor de inversión (en letras)")
        doc.setFont('times', 'normal');
        doc.text(x + 15, y + 102, "\n********************************************************************\n" + datos[0][6] + " **********(Q" + intmonto + ")");// monto en letras

        // doc.text(x + 29, y + 35, datos[0][4]); //Dpi
        // doc.text(x + 30, y + 35, datos[0][5]); //telefono
        // doc.text(x + 50, y + 45, datos[0][3]); //Direccion
        // doc.text(x + 30, y + 60, 'Q.' + intmonto);//monto +15 de texto
        var interescant = (parseFloat(intmonto.replace(/,/g, '')) * (intere / 100)).toFixed(2); //interes cantidad

        doc.setFont('times', 'bold');
        doc.text(x + 20, y + 122, "Fecha de\nInversión");//FECHA INICIO
        doc.text(x + 50, y + 122, "Plazo"); //PLAZO
        doc.text(x + 72, y + 122, "Tasa de interés\n       Anual");//interes
        doc.text(x + 105, y + 122, "Fecha de\nvencimiento");//FECHA vence
        doc.text(x + 135, y + 122, "Intereses a\ndevengar"); //interes cantidad
        doc.text(x + 135, y + 135, "\n\nTotal a pagar"); //total

        doc.setFont('times', 'normal');
        doc.text(x + 20, y + 122, "\n\n\n" + fechaini);//FECHA INICIO
        doc.text(x + 50, y + 122, "\n\n\n" + datos[0][8] + ' días.'); //PLAZO
        doc.text(x + 81, y + 122, "\n\n\n" + intere + '%');//interes
        doc.text(x + 105, y + 122, "\n\n\n" + fechafin);//FECHA vence
        doc.text(x + 135, y + 122, "\n\n\n" + 'Q.' + interescant); //interes cantidad
        doc.text(x + 135, y + 140, "\n\n\n" + 'Q.' + (parseFloat(intmonto.replace(/,/g, '')) + parseFloat(interescant)).toFixed(2)); //total

        doc.text(x + 15, y + 150, "La inversión dejara de devengar intereses a partir del día siguiente\na la fecha de su vencimiento")

        doc.text(x + 15, y + 174, "______________________\n  Sebastián Juan Baltazar\n   Representante Legal");
        doc.text(x + 100, y + 174, "  ________________________\n" + datos[0][1] + "\n        Inversionista");

        var beneficiarios = "";
        let dpi = "";
        let decripc = "";
        let tel = "";
        const max_caracteres = 10;
        // console.log(datos[1]);
        for (let i = 1; i < datos[1].length; i++) {
            beneficiarios = beneficiarios + datos[1][i]['nombre'];
            dpi = dpi + datos[1][i]['dpi'];
            decripc = decripc + datos[1][i]['codparent'];
            tel = tel + datos[1][i]['telefono'];
            if (i !== ((datos[1].length) - 1)) {
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

        console.log(datos[1].length)

        // if (datos[1].length > 0) {
        //     doc.setFont('helvetica', 'bold');
        //     doc.text(x + 55, y + 120, 'DATOS DE BENEFICIARIOS');
        //     doc.setFont('helvetica', 'normal');

        //     doc.setFont('helvetica', 'bold');
        //     doc.text(x + 20, y + 130, 'Nombre Completo');
        //     doc.text(x + 95, y + 130, 'Parentesco');
        //     doc.text(x + 130, y + 130, 'DPI');
        //     // doc.text(175,y+ 40, 'NO. TELEFONO');
        //     doc.setFont('helvetica', 'normal');

        //     doc.text(x + 20, y + 140, splitTitle); //beneficiario
        //     doc.text(x + 95, y + 140, decripc2); //parentezco
        //     doc.text(x + 120, y + 140, dpi2); //DPI beneficiario
        //     // doc.text(175,y+ 44, tel2); //telefono
        // }

        doc.autoPrint();
        window.open(doc.output('bloburl'))
    }
}

function impresion_certificado_construfuturo(datos) {
    // console.log(datos);
    // return;
    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);

    const y = 45;
    const x = 35;
    //texto en negrita
    doc.setFont('helvetica', 'normal');
    doc.text(x, y + 16, datos[0][1]); //nombre del cliente
    doc.text(x, y + 24, datos[0][4]); /* dpi */       doc.text(x + 100, y + 24, datos[0][2]); // cuenta
    doc.text(x, y + 32, datos[0][3]); /* direccion */ doc.text(x + 100, y + 32, datos[0][5]); // telefono
    doc.setFontSize(9)
    doc.text(x + 10, y + 50, 'Q.' + intmonto + '                ' + "    " + '                ' + datos[0][8] + 'D' + '           ' + fechafin + '               ' + intere + '%'); // monto
    // doc.text(x + 20, y + 70, 'Monto en letras:');

    // doc.text(x + 20, y + 95, 'Fecha de Apertura:');

    // //texto normal
    // doc.setFont('helvetica', 'normal');
    // doc.text(x + 85, y + 10, datos[0][1]); //nombre del cliente
    // doc.text(x + 85, y + 20, datos[0][2]); //cuenta
    // doc.text(x + 29, y + 35, datos[0][4]); //Dpi
    // doc.text(x + 120, y + 35, datos[0][5]); //telefono

    // doc.text(x + 50, y + 45, datos[0][3]); //Direccion
    // doc.text(x + 35, y + 60, 'Q.' + intmonto);//monto +15 de texto
    // // doc.text(x + 60, y + 70, datos[0][6]);// monto en letras

    // doc.text(x + 35, y + 85, datos[0][8] + ' días.'); //PLAZO
    // doc.text(x + 115, y + 85, intere + '%');//interes
    // // doc.text(x + 65, y + 95, fechaini);//FECHA INICIO
    // doc.text(x + 148, y + 95, fechafin);//FECHA vence

    doc.setFontSize(11)

    doc.text(x + 17, y + 182, "Canton Jactzal Nebaj, El Quiché. " + datos[0][22])

    doc.setFontSize(10)
    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    let porcent = "";
    const max_caracteres = 10;
    // console.log(datos[1]);
    for (let i = 1; i < datos[1].length; i++) {
        beneficiarios = beneficiarios + datos[1][i]['nombre'];
        dpi = dpi + datos[1][i]['dpi'];
        decripc = decripc + datos[1][i]['codparent'];
        tel = tel + datos[1][i]['telefono'];
        porcent = porcent + datos[1][i]['porcentaje'];
        if (i !== ((datos[1].length) - 1)) {
            beneficiarios = beneficiarios + "\n\n";
            dpi = dpi + "\n";
            decripc = decripc + "\n\n";
            tel = tel + "\n";
            porcent = porcent + "\n\n";
        }

    }
    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    var dpi2 = doc.splitTextToSize(dpi, 180);
    var decripc2 = doc.splitTextToSize(decripc, 180);
    var tel2 = doc.splitTextToSize(tel, 180);

    console.log(datos[1].length)

    if (datos[1].length > 0) {
        // doc.setFont('helvetica', 'bold');
        // // doc.text(x + 55, y + 120, 'DATOS DE BENEFICIARIOS');
        // doc.setFont('helvetica', 'normal');

        // doc.setFont('helvetica', 'bold');
        // doc.text(x + 20, y + 130, 'Nombre Completo');
        // doc.text(x + 95, y + 130, 'Parentesco');
        // doc.text(x + 130, y + 130, 'DPI');
        // doc.text(175,y+ 40, 'NO. TELEFONO');
        doc.setFont('helvetica', 'normal');

        doc.text(x - 10, y + 75, splitTitle); //beneficiario
        doc.text(x + 95, y + 75, decripc2); //parentezco
        doc.text(x + 148, y + 76, porcent); //porcentaje beneficiario
        // doc.text(175,y+ 44, tel2); //telefono
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}


function impresion_recibo_dep_ret_construfuturo(datos) {
    // console.log(datos);
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

        if (datos[23] == 'D') {

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
            doc.text(margenizquierdo + 12, 57, 'Q.' + datos[3]); // Cantidad en letras

            doc.text(margenizquierdo + 12, 64, datos[2]); //CUENTA


            if (datos[28] == 'Ahorro corriente') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 146, 73, datos[3]);
                doc.text(margenizquierdo + 146, 98, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro a Plazo fijo') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 146, 78, datos[3]);
                doc.text(margenizquierdo + 146, 98, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro programado') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 146, 83, datos[3]);
                doc.text(margenizquierdo + 146, 98, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro infantil') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 146, 88, datos[3]);
                doc.text(margenizquierdo + 146, 98, datos[3]); // TOTAL
            }

            doc.setFontSize(10);
            let saldoActual1 = parseFloat(datos[24]); // SALDO ACTUAL
            let deposito = parseFloat(datos[25]); // SALDO ANTERIOR
            let saldoAnterior1 = saldoActual1 - deposito;
            doc.text(46, 81 + 5, ' ' + saldoAnterior1); //SALDO ANTERIOR
            doc.text(46, 88 + 5, ' ' + datos[3]); //ABONO
            doc.text(46, 95 + 5, ' ' + datos[24]); //SALDO ACTUAL

            doc.text(46, 107, datos[11]);
        }

        if (datos[23] == 'R') {

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


            if (datos[28] == 'Ahorro corriente') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 146, 50, datos[3]);
                // doc.text(margenizquierdo + 146, 98, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro a Plazo fijo') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 146, 55, datos[3]);
                // doc.text(margenizquierdo + 146, 98, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro programado') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 146, 60, datos[3]);
                // doc.text(margenizquierdo + 146, 98, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro infantil') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 146, 65, datos[3]);
                // doc.text(margenizquierdo + 146, 98, datos[3]); // TOTAL
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

function impresion_libreta_construfuturo(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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
    var y = 26.5;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        // console.log(datos[1][i]);
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (5 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(22, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        ctipdoc = datos[1][i]['ctipdoc'];
        //*** */
        cont = 0;
        if (tiptr == "D") {
            cont = i + 1;
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito";
            doc.text(46, pos, transaccion);
            doc.text(79, pos, "" + pad(currency(monto)));
            doc.text(100, pos, "");
        }
        if (tiptr == "R") {
            cont = i + 1;
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro";
            doc.text(46, pos, transaccion);
            doc.text(80, pos, "");
            doc.text(102, pos, "" + pad(currency(monto)));
        }
        doc.text(130, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            // console.log("aki papu");
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        cont++;
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

function impresion_recibo_dep_ret_edificando(datos) {
    // console.log(datos);
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
        doc.text(margenizquierdo + 70, ini, 'Monto: Q ' + datos[3]);

        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Operación: ' + datos[6]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Producto: ' + datos[16]);

        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Fecha operación: ' + datos[10]);

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}


function impresion_libreta_edificando(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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

function impresion_liquidacion_certificado_multinorte(datos) {
    console.log(datos);
    alert('Insertar hoja de certificado')
    var opciones = {
        orientation: 'P',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);
    var ini = 30;
    var margen = 10;
    var intletras = numeroALetras(parseFloat(datos[7]).toFixed(2)); //interes en letras

    doc.text(margen + 10, ini, 'Certificado. ' + datos[2]);
    doc.text(margen + 97, ini, 'Fecha. ' + datos[3]);
    doc.text(margen + 145, ini, 'Codigo de cuenta: ' + datos[4]);

    ini = ini + 10;
    doc.text(margen + 10, ini, 'Cliente: ' + datos[5]);

    ini = ini + 10;
    doc.text(margen + 10, ini, 'Monto apertura: ' + datos[6]);
    doc.text(margen + 130, ini, 'Interes: ' + datos[7]);
    ini = ini + 12;
    doc.text(margen + 10, ini, 'ISR: ' + parseFloat(datos[8]).toFixed(2));
    ini = ini + 10;
    doc.text(margen + 10, ini, 'Monto en letras: ' + intletras);
    ini = ini + 10;
    doc.text(margen + 10, ini, 'Recibo: ' + datos[10]);
    ini = ini + 60;

    doc.autoPrint();
    window.open(doc.output('bloburl'));
}

function impresion_comprobante_certificado_multinorte(datos) {
    var opciones = {
        orientation: 'P',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);
    var i = 1;
    var ini = 30;
    while (i < 4) {
        doc.text(10, ini, 'Certificado. ' + datos[2]);
        doc.text(97, ini, 'Fecha. ' + datos[3]);
        doc.text(145, ini, 'Codigo de cuenta' + datos[4]);

        ini = ini + 10;
        doc.text(10, ini, 'Cliente: ' + datos[5]);

        ini = ini + 10;
        doc.text(10, ini, 'Monto apertura: ' + datos[6]);
        doc.text(130, ini, 'Interes: Q ' + datos[7]);

        ini = ini + 12;
        doc.text(10, ini, 'ISR: ' + datos[8]);
        ini = ini + 10;
        doc.text(40, ini, 'Monto en letras: ' + datos[9]);
        ini = ini + 10;
        doc.text(10, ini, 'Recibo: ' + datos[10]);
        ini = ini + 60;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
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

        if (datos[23] == 'D') {
            doc.text(78, ini, datos[14]);//dia
            doc.text(96, ini, datos[13]);//fechas
            doc.text(110, ini, datos[12]);//fechas
            ini = ini + 10;
            doc.text(40, ini, datos[7]); // CLIENTE
            ini = ini + 25;
            doc.text(margenizquierdo + 12, ini, 'DEPOSITO DE AHORRO A LA CUENTA ' + datos[2] + ' - ' + datos[16]);
            doc.text(152, 102, 'TOTAL:            Q. ' + datos[3]);
            doc.text(65, 111, datos[11]);
        }
        if (datos[23] == 'R') {
            doc.text(78, ini, datos[14]);//dia
            doc.text(96, ini, datos[13]);//fechas
            doc.text(110, ini, datos[12]);//fechas
            ini = ini + 10;
            doc.text(45, ini, 'COOPERATIVA INTEGRAL DE AHORRO Y CRÉDITO COFAI, R.L.'); // CLIENTE
            ini = ini + 25;
            doc.text(20, ini, 'RETIRO DE AHORRO DE LA CUENTA ' + datos[2] + ' - ' + datos[16]);
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

function impresion_certificado_edificando(datos) {
    console.log(datos);
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    datosAsoc = datos[4];
    doc.setFontSize(9);

    // Convierte el texto a primeras letras en mayusculaS
    function Mayusini(texto) {
        return texto
            .toLowerCase()
            .replace(/\b\w/g, (letra) => letra.toUpperCase());
    }

    let montoEnLetras = Mayusini(datos[0][6]);
    let nomcli = Mayusini(datos[0][1]);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);
    var oficina = datos[0][17];

    const y = 50;
    const x = -10;

    //texto normal
    doc.setFont("Arial", "bold");
    doc.text(x + 155, y + 3, datos[0][2]); //CUENTA
    // doc.text(x + 75, y, datos[3]); //CODIGO DEL CLIENTE
    doc.text(x + 60, y + 10, nomcli); //nombre del cliente
    doc.text(x + 185, y + 10, datos[0][4]); //Dpi

    doc.text(x + 55, y + 18, datos[0][3]); //Direccion
    doc.text(x + 185, y + 18, datos[0][5]); //telefono
    doc.text(x + 60, y + 25, montoEnLetras);// monto en letras
    doc.text(x + 185, y + 25, intmonto);//monto +15 de texto
    doc.text(x + 60, y + 31, datos[0][8] + ' días.'); //PLAZO
    doc.text(x + 130, y + 31, fechafin);//FECHA vence
    doc.text(x + 195, y + 31, intere + ' % ');//interes

    var lugaroficina = (oficina == "001") ? "Paraje xecoxom ll, Aldea Paxtocá, Totonicapán" : "Paraje xecoxom ll, Aldea Paxtocá, Totonicapán";
    doc.text(x + 60, 192, lugaroficina + "  " + datos[0][15]);

    //    doc.text( x+85, y+20, datos[0][2]); //cuenta
    //    doc.text( x+65, y+95, fechaini);//FECHA INICIO

    var i = 1;
    var ini = 100;
    while (i < datos[1].length) {
        doc.text(50, ini, datos[1][i]['nombre']);
        // doc.text(50, ini + 10, datos[1][i]['codparent']);
        // doc.text(175, ini, datos[1][i]['dpi']);
        doc.text(175, ini, datos[1][i]['dpi']);
        doc.text(50, ini + 5, datos[1][i]['codparent']);
        doc.text(125, ini + 5, datos[1][i]['direccion']);

        ini = ini + 25;
        i++;
    }
    // doc.text(x + 150, 240, lugaroficina + "  " + datos[0][15]);

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_libreta_cofai(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    console.log("impresion libreta cofai")
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
    //var i = 0;
    var i = posini;
    var tiptr;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        // console.log(datos[1][i]);
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
        doc.text(177, pos, "" + datos[1][i]['codusu']);
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
function impresion_certificado_cofai(datos) {
    // console.log(datos);
    // return;
    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado cofai");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);

    const y = 68;
    const x = 35;
    //texto en negrita
    var vfecha = datos[0][9].split('-');
    var mes = convertir_mes(vfecha[1]);
    doc.setFontSize(7)
    doc.setFont('helvetica', 'bold');
    doc.text(x + 30, y + 16, vfecha[2] + ' de ' + mes + ' de ' + vfecha[0]);//FECHA INICIO
    doc.text(x + 115, y + 16, datos[0][2]); // cuenta
    doc.text(x + 50, y + 24, datos[0][1]); //nombre del cliente
    doc.text(x + 35, y + 32, datos[0][3]); //direccion
    doc.text(x + 35, y + 39, datos[0][6]);// monto en letras
    doc.text(x + 130, y + 40, 'Q.' + intmonto);//monto +15 de texto
    doc.text(x + 35, y + 47, datos[0][8] + ' días.'); //PLAZO
    doc.text(x + 115, y + 47, datos[0][23]);//FECHA vence
    doc.text(x + 115, y + 55, intere + '%');//interes



    // doc.text(x + 10, y + 50, 'Q.' + intmonto + '                ' + "    " + '                ' + datos[0][8] + 'D' + '           ' + fechafin + '               ' + intere + '%'); // monto
    // doc.text(x + 20, y + 70, 'Monto en letras:');

    // doc.text(x + 20, y + 95, 'Fecha de Apertura:');

    // //texto normal
    // doc.setFont('helvetica', 'normal');
    // doc.text(x + 85, y + 10, datos[0][1]); //nombre del cliente
    // doc.text(x + 85, y + 20, datos[0][2]); //cuenta
    // doc.text(x + 29, y + 35, datos[0][4]); //Dpi
    // doc.text(x + 120, y + 35, datos[0][5]); //telefono

    // doc.text(x + 50, y + 45, datos[0][3]); //Direccion
    // doc.text(x + 35, y + 60, 'Q.' + intmonto);//monto +15 de texto

    doc.setFontSize(11)

    // doc.text(x + 17, y + 182, "Canton Jactzal Nebaj, El Quiché. " + datos[0][22])


    doc.setFontSize(10)
    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    let porcent = "";
    const max_caracteres = 10;
    console.log(datos[1]);
    for (let i = 1; i < datos[1].length; i++) {
        beneficiarios = beneficiarios + datos[1][i]['nombre'];
        dpi = dpi + datos[1][i]['dpi'];
        decripc = decripc + datos[1][i]['codparent'];
        tel = tel + datos[1][i]['telefono'];
        porcent = porcent + datos[1][i]['porcentaje'];
        if (i !== ((datos[1].length) - 1)) {
            beneficiarios = beneficiarios + "\n\n";
            dpi = dpi + "\n";
            decripc = decripc + "\n\n";
            tel = tel + "\n";
            porcent = porcent + "\n\n";
        }

    }
    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    var dpi2 = doc.splitTextToSize(dpi, 180);
    var decripc2 = doc.splitTextToSize(decripc, 180);
    var tel2 = doc.splitTextToSize(tel, 180);

    console.log(datos[1].length)

    if (datos[1].length > 0) {
        // doc.setFont('helvetica', 'bold');
        // // doc.text(x + 55, y + 120, 'DATOS DE BENEFICIARIOS');
        // doc.setFont('helvetica', 'normal');

        // doc.setFont('helvetica', 'bold');
        // doc.text(x + 20, y + 130, 'Nombre Completo');
        // doc.text(x + 95, y + 130, 'Parentesco');
        // doc.text(x + 130, y + 130, 'DPI');
        // doc.text(175,y+ 40, 'NO. TELEFONO');
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8)

        doc.text(x + 60, y + 62, splitTitle); //beneficiario
        // doc.text(x + 70, y + 62, decripc2); //parentezco
        doc.text(x + 80, y + 62, porcent + '%'); //porcentaje beneficiario
        // doc.text(175,y+ 44, tel2); //telefono
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_primavera(datos) {
    console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 35;
    var margenizquierdo = 18;
    while (i < 2) {
        doc.setFontSize(10);
        doc.text(margenizquierdo, ini, 'Cuenta: ' + datos[2]);
        doc.text(margenizquierdo + 80, ini, 'Fecha: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo + 80, ini, 'Monto: Q ' + datos[3]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operación: ' + datos[6]);
        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Producto: ' + datos[16]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, datos[10]);

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_libreta_kumooles(datos, resta, ini, posini, posfin, saldoo, numi, file) {
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


function impresion_recibo_dep_ret_kumooles(datos) {
    console.log(datos);
    // console.log("impresion recibo kumooles")
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

        if (datos[23] == 'D') {

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
            doc.text(margenizquierdo * 4 + 82, 36, datos[21]); //CODCLIENTE
            doc.text(margenizquierdo + 12, 42, datos[26]); //Dirección

            if (datos[27] == '04') {
                doc.setFontSize(10);
                doc.text(68, 88 + 6, datos[3]);
                doc.text(68, 126, datos[3]); // TOTAL
            }
            if (datos[27] == '85') {
                doc.setFontSize(10);
                doc.text(68, 88 + 6 * 2, datos[3]);
                doc.text(68, 126, datos[3]); // TOTAL
            }
            if (datos[27] == '31') {
                doc.setFontSize(10);
                doc.text(68, 88 + 6 * 3, datos[3]);
                doc.text(68, 126, datos[3]); // TOTAL
            }
            if (datos[27] == '18') {
                doc.setFontSize(10);
                doc.text(68, 89 + 6 * 4, datos[3]);
                doc.text(68, 126, datos[3]); // TOTAL
            }

            doc.setFontSize(10);
            let saldoActual1 = parseFloat(datos[24]); // SALDO ACTUAL
            let deposito = parseFloat(datos[25]); // SALDO ANTERIOR
            let saldoAnterior1 = saldoActual1 - deposito;
            doc.text(137, 50 + 2, ' ' + saldoAnterior1); //SALDO ANTERIOR
            doc.text(137, 56 + 2, ' ' + datos[3]); //ABONO
            doc.text(137, 63 + 2, ' ' + datos[24]); //SALDO ACTUAL

            // doc.text(46, 107, datos[11]);
        }

        if (datos[23] == 'R') {

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
            doc.text(margenizquierdo * 4 + 60, 49, datos[15]); // DPI CLIENTE

            doc.text(margenizquierdo + 12, 43, datos[2]); //CUENTA


            if (datos[28] == 'Ahorro corriente') {
                doc.setFontSize(10);
                doc.text(83, 72, datos[3]);
                doc.text(83, 124, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro a Plazo fijo') {
                doc.setFontSize(10);
                doc.text(83, 78, datos[3]);
                doc.text(83, 124, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro programado') {
                doc.setFontSize(10);
                doc.text(83, 84, datos[3]);
                doc.text(83, 124, datos[3]); // TOTAL
            }
            if (datos[28] == 'Ahorro infantil') {
                doc.setFontSize(10);
                doc.text(83, 92, datos[3]);
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



function impresion_certificado_kumooles(datos) {
    console.log(datos);
    // return;
    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    //console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(13);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 15; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);
    const fecha = datos[0][15];
    const [anio, mes, dia] = fecha.split("-")
    mesp = convertir_mes(mes)


    const y = 93;
    const x = 55;
    const y2 = 13;
    //texto en negrita
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    // // Línea 1: Nombre y No. de cuenta (misma línea, con espacio)
    // doc.text(x, y - 5, String(datos[0][1] || '')); // nombre del cliente
    // doc.text(x + 102, y - 5, String(datos[0][2] || '')); // No. de cuenta

    // // Línea 2: Dirección, DPI y Teléfono (misma línea, con espacios)
    // doc.text(x, y + 3, String(datos[0][3] || '')); // dirección
    // doc.text(x + 65, y + 3, String(datos[0][4] || '')); // DPI
    // doc.text(x + 105, y + 3, String(datos[0][5] || '')); // teléfono

    // // Línea 3: Monto en letras y monto numérico (misma línea)
    // doc.text(x, y + 11, String(datos[0][6] || '')); // monto en letras
    // doc.text(x + 100, y + 11, String(intmonto || '')); // monto numérico

    // // Línea 4: Plazo y tasa de interés (misma línea)
    // doc.text(x, y + 20, String((datos[0][8] || '') + ' días')); // plazo
    // doc.text(x + 65, y + 20, String((!isNaN(intere) ? intere : intere || ''))); // interés

    // // Línea 5: Fecha de vencimiento (última línea)
    // doc.text(x + 10, y + 30, String(fechafin || '')); // fecha de fin


    doc.text(x + 15, y + 130, dia + "                               " + mesp + "                              " + anio);




    // doc.text(x + 50, y + y2 * 4, fechaini);

    //texto normal
    doc.setFont('helvetica', 'normal');
    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    let porcent = "";
    const max_caracteres = 10;
    // console.log(datos[1]);
    for (let i = 1; i < datos[1].length; i++) {
        beneficiarios = beneficiarios + datos[1][i]['nombre'];
        dpi = dpi + datos[1][i]['dpi'];
        decripc = decripc + datos[1][i]['codparent'];
        tel = tel + datos[1][i]['telefono'];
        porcent = porcent + datos[1][i]['porcentaje'];
        if (i !== ((datos[1].length) - 1)) {
            beneficiarios = beneficiarios + "\n";
            dpi = dpi + "\n";
            decripc = decripc + "\n";
            tel = tel + "\n";
            porcent = porcent + "\n";
        }

    }
    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    var dpi2 = doc.splitTextToSize(dpi, 180);
    var decripc2 = doc.splitTextToSize(decripc, 180);
    var tel2 = doc.splitTextToSize(tel, 180);
    var porcent2 = doc.splitTextToSize(porcent, 180);

    // console.log(datos[1].length)

    if (datos[1].length > 0) {
        doc.setFontSize(9);
        doc.setFont('helvetica', 'normal');
        // doc.text(x + 20, y + 130, 'Nombre Completo');
        // doc.text(x + 95, y + 130, 'Parentesco');
        // doc.text(x + 130, y + 130, 'DPI');
        // doc.text(175,y+ 40, 'NO. TELEFONO');
        for (let i = 0; i < splitTitle.length; i++) {
            doc.text(x, y + 41 + (i * 6), splitTitle[i]); //beneficiario
            doc.text(x + 65, y + 41 + (i * 6), decripc2[i]); //parentezco
            doc.text(x + 120, y + 41 + (i * 6), porcent2[i] + "%"); //porcentaje beneficiario

        }
        //beneficiario
        //doc.text(x + 95, y + 140, decripc2); //parentezco
        //doc.text(x + 120, y + 140, dpi2); //DPI beneficiario
        // doc.text(175,y+ 44, tel2); //telefono
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_micasa(datos) {
    //// console.log(datos);
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
        doc.text(margenizquierdo, ini, 'Cuenta: ' + datos[2]);
        doc.text(margenizquierdo + 80, ini, 'Fecha: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo + 80, ini, 'Monto: Q ' + datos[3]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operación: ' + datos[6]);
        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Producto: ' + datos[16]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, datos[10]);

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}


function impresion_certificado_codelago(datos) {
    // console.log(datos);
    // return;
    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(13);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 20; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);

    const y = 80;
    const x = 10;
    //texto en negrita
    doc.setFontSize(11);
    doc.setFont('helvetica', 'normal');
    doc.text(x + 30, y + 12 + 8, 'Nombre: ' + datos[0][1]);
    doc.text(x + 130, y + 12 + 8, 'Cuenta No. ' + datos[0][2]);
    doc.text(x + 120, y + 16 + 8, "DPI: " + datos[0][4]);
    doc.text(x + 160, y + 16 + 8, 'Tel: ' + datos[0][5]);

    doc.text(x + 30, y + 16 + 8, 'Dirección: ' + datos[0][3]);
    doc.text(x + 145, y + 20 + 8, 'Monto: ' + 'Q.' + intmonto);
    doc.text(x + 30, y + 20 + 8, 'Monto en letras: ' + datos[0][6]);

    doc.text(x + 30, y + 24 + 8, 'A Plazo ' + datos[0][8] + ' días.');
    doc.text(x + 110, y + 28 + 8, 'Interes: ' + intere + '%' + ' Anual');
    doc.text(x + 60, y + 24 + 8, 'Fecha de Apertura: ' + fechaini);
    doc.text(x + 30, y + 28 + 8, 'Fecha de Vencimiento: ' + fechafin);

    // Definir tu párrafo

    doc.setFontSize(11);
    doc.setFont('helvetica', 'normal');
    let parrafo1 = 'La Cooperativa Integtral de Ahorro y Credito "Desarrollo Empresarial del Lago" Responsabilidad Limitada (CODELAGO, R.L.), CERTIFICA: Que ha recibido el ahorro a plazo fijo y entregara al asiociado a partir del día hábil siguiente a la fecha de su vencimiento, la cantidad depositada y los interes devengados.';
    // Ancho
    let anchoTexto = 155;
    let linea1 = doc.splitTextToSize(parrafo1, anchoTexto);
    // let linea2 = doc.splitTextToSize(parrafo2, anchoTexto);

    doc.text(linea1, x + 30, y - 1, { align: "justify" });
    // doc.text(linea2, x + 30, y + 62, { align: "justify" });

    doc.text(x + 55, y + 140, "Lugar y fecha:  San Juan la Laguna, Sololá " + datos[0][15]); //Fecha de impresion
    // doc.text(x + 30, y + 150, "F.____________________________");
    // doc.text(x + 40, y + 154, "Firma del Asociado");
    // doc.text(x + 120, y + 154, "Representante Legal de la Cooperativa");
    // doc.text(x + 120, y + 150, "F._____________________________");

    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    let direccion = "";
    const max_caracteres = 10;
    // console.log(datos[1]);
    for (let i = 1; i < datos[1].length; i++) {
        beneficiarios = beneficiarios + datos[1][i]['nombre'];
        dpi = dpi + datos[1][i]['dpi'];
        decripc = decripc + datos[1][i]['codparent'];
        tel = tel + datos[1][i]['telefono'];
        direccion = direccion + datos[1][i]['direccion'];
        if (i !== ((datos[1].length) - 1)) {
            beneficiarios = beneficiarios + "; ";
            dpi = dpi + "; ";
            decripc = decripc + "; ";
            tel = tel + "; ";
            direccion = direccion + "; ";
        }

    }
    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    var dpi2 = doc.splitTextToSize(dpi, 180);
    var decripc2 = doc.splitTextToSize(decripc, 180);
    var tel2 = doc.splitTextToSize(tel, 180);
    var direccion2 = doc.splitTextToSize(direccion, 180);

    console.log(datos[1].length)

    if (datos[1].length > 0) {
        doc.setFontSize(9);
        doc.setFont('helvetica', 'normal');
        doc.text(x + 30, y + 36 + 8, 'BENEFICIARIOS(A):');
        doc.text(x + 30, y + 40 + 8, 'Nombre (s):  ' + splitTitle);
        doc.text(x + 30, y + 52 + 8, 'Parentesco:  ' + decripc2);
        doc.text(x + 30, y + 44 + 8, 'No. DPI:  ' + dpi2);
        doc.text(x + 30, y + 48 + 8, 'Dirección:  ' + direccion2);
        // doc.text(175,y+ 40, 'NO. TELEFONO');
        doc.setFont('helvetica', 'normal');

    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}


function impresion_recibo_dep_ret_codelago(datos) {
    //// console.log(datos);
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
        // reducir tamaño de fuente y espacios para contenido más compacto
        doc.setFontSize(8);
        doc.text(margenizquierdo, ini, 'Cuenta: ' + datos[2]);
        doc.text(margenizquierdo + 70, ini, 'Fecha: ' + datos[4]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo + 70, ini, 'Monto: Q ' + datos[3]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Operación: ' + datos[6]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Producto: ' + datos[16]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8]);

        ini = ini + 3;
        doc.setFontSize(7);
        doc.text(margenizquierdo, ini, datos[10]);

        ini = ini + 30;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}


function impresion_libreta_codelago(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log("impresion libreta main")
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
    //var i = 0;
    var i = posini;
    var tiptr;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var cnumdoc
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        // console.log(datos[1][i]);
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


function impresion_liquidacion_certificado_edificandosueños(datos) {
    // console.log(datos);
    // alert('Insertar hoja de certificado')
    var opciones = {
        orientation: 'P',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(10);
    var i = 1;
    var ini = 30;
    var margenizquierdo = 40;

    while (i < 2) {
        doc.text(margenizquierdo + 10, ini, 'Certificado. ' + datos[2]);
        doc.text(margenizquierdo + 55, ini, 'Fecha. ' + datos[3]);
        ini = ini + 5;
        doc.text(margenizquierdo + 10, ini, 'Codigo de cuenta: ' + datos[4]);

        ini = ini + 5;
        doc.text(margenizquierdo + 10, ini, 'Cliente: ' + datos[5]);

        ini = ini + 5;
        doc.text(margenizquierdo + 10, ini, 'Monto apertura: ' + datos[6]);
        doc.text(margenizquierdo + 80, ini, 'Interes: Q. ' + parseFloat(datos[7]).toFixed(2));
        ini = ini + 5;
        doc.text(margenizquierdo + 10, ini, 'ISR: ' + parseFloat(datos[8]).toFixed(2));
        ini = ini + 5;
        doc.text(margenizquierdo + 10, ini, 'Monto en letras: ');
        ini = ini + 5;
        doc.setFontSize(8);
        doc.text(margenizquierdo + 10, ini, datos[9]);
        ini = ini + 5;
        doc.setFontSize(10);
        doc.text(margenizquierdo + 10, ini, 'Recibo: ' + datos[10]);
        // ini = ini + 60;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_certificado_adifsa(datos) {
    // console.log(datos);
    // return;
    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(13);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 10; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);

    const y = 80;
    let ini = 88;
    const x = -5;
    //texto en negrita
    doc.setFontSize(11);
    doc.setFont('helvetica', 'normal');

    ini = ini+28;
    doc.text(x + 30,ini, 'Nombre: ' + datos[0][1]);
    doc.text(x + 130,ini, 'Cuenta No. ' + datos[0][2]);

    ini = ini + 4;
    doc.text(x + 30, ini, "DPI: " + datos[0][4]);
    doc.text(x + 160,ini, 'Tel: ' + datos[0][5]);

    ini = ini + 4;
    doc.text(x + 30, ini, 'Dirección: ' + datos[0][3]);

    ini = ini + 4;
    doc.text(x + 30, ini, 'Monto: ' + 'Q.' + intmonto);
    doc.text(x + 70, ini, 'Monto en letras: ' + datos[0][6]);

    ini = ini + 4;
    doc.text(x + 30, ini, 'A Plazo ' + datos[0][8] + ' días.');
    doc.text(x + 75, ini, 'Fecha de Apertura: ' + fechaini);
    
    ini = ini + 4;
    doc.text(x + 30, ini, 'Fecha de Vencimiento: ' + fechafin);
    doc.text(x + 110,ini, 'Interes: ' + intere + '%' + ' Anual');

    // Definir tu párrafo

    // doc.setFontSize(11);
    // doc.setFont('helvetica', 'normal');
    // let parrafo1 = 'La Cooperativa Integtral de Ahorro y Credito "Desarrollo Empresarial del Lago" Responsabilidad Limitada (CODELAGO, R.L.), CERTIFICA: Que ha recibido el ahorro a plazo fijo y entregara al asiociado a partir del día hábil siguiente a la fecha de su vencimiento, la cantidad depositada y los interes devengados.';
    // // Ancho
    // let anchoTexto = 155;
    // let linea1 = doc.splitTextToSize(parrafo1, anchoTexto);
    // // let linea2 = doc.splitTextToSize(parrafo2, anchoTexto);

    // doc.text(linea1, x + 30, y - 1, { align: "justify" });
    // doc.text(linea2, x + 30, y + 62, { align: "justify" });

    // doc.text(x + 55, y + 140, "Lugar y fecha:  San Juan la Laguna, Sololá " + datos[0][15]); //Fecha de impresion
    // doc.text(x + 30, y + 150, "F.____________________________");
    // doc.text(x + 40, y + 154, "Firma del Asociado");
    // doc.text(x + 120, y + 154, "Representante Legal de la Cooperativa");
    // doc.text(x + 120, y + 150, "F._____________________________");

    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    let direccion = "";
    const max_caracteres = 10;
    // console.log(datos[1]);
    for (let i = 1; i < datos[1].length; i++) {
        beneficiarios = beneficiarios + datos[1][i]['nombre'];
        dpi = dpi + datos[1][i]['dpi'];
        decripc = decripc + datos[1][i]['codparent'];
        tel = tel + datos[1][i]['telefono'];
        direccion = direccion + datos[1][i]['direccion'];
        if (i !== ((datos[1].length) - 1)) {
            beneficiarios = beneficiarios + "; ";
            dpi = dpi + "; ";
            decripc = decripc + "; ";
            tel = tel + "; ";
            direccion = direccion + "; ";
        }

    }
    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    var dpi2 = doc.splitTextToSize(dpi, 180);
    var decripc2 = doc.splitTextToSize(decripc, 180);
    var tel2 = doc.splitTextToSize(tel, 180);
    var direccion2 = doc.splitTextToSize(direccion, 180);

    console.log(datos[1].length)

    if (datos[1].length > 0) {
        doc.setFontSize(9);
        doc.setFont('helvetica', 'normal');
        doc.text(x + 30, y + 36 + 16, 'BENEFICIARIOS(A):');
        doc.text(x + 30, y + 40 + 16, 'Nombre (s):  ' + splitTitle);
        doc.text(x + 30, y + 52 + 16, 'Parentesco:  ' + decripc2);
        doc.text(x + 30, y + 44 + 16, 'No. DPI:  ' + dpi2);
        doc.text(x + 30, y + 48 + 16, 'Dirección:  ' + direccion2);
        // doc.text(175,y+ 40, 'NO. TELEFONO');
        doc.setFont('helvetica', 'normal');

    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_cemadec(datos) {
    //// console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 33;
    var rec2 = 100;
    var margenizquierdo = 15;
    while (i < 2) {
        doc.setFontSize(8);

        doc.text(margenizquierdo, ini, 'Cuenta: ' + datos[2]);
        doc.text(margenizquierdo + rec2, ini, 'Cuenta: ' + datos[2]);

        
        doc.text(margenizquierdo + 55, ini, 'Fecha: ' + datos[4]);
        doc.text(margenizquierdo + 55 + rec2, ini, 'Fecha: ' + datos[4]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        doc.text(margenizquierdo + rec2, ini, 'Cliente: ' + datos[7]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo + rec2, ini, 'No. Docto: ' + datos[5]);

        doc.text(margenizquierdo + 55, ini, 'Monto: Q ' + datos[3]);
        doc.text(margenizquierdo + 55 + rec2, ini, 'Monto: Q ' + datos[3]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Operación: ' + datos[6]);
        doc.text(margenizquierdo + rec2, ini, 'Operación: ' + datos[6]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Producto: ' + datos[16]);
        doc.text(margenizquierdo + rec2, ini, 'Producto: ' + datos[16]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8]);
        doc.text(margenizquierdo + rec2, ini, 'Operador: ' + datos[8]);

        ini = ini + 3;
        doc.setFontSize(7);
        doc.text(margenizquierdo, ini, datos[10]);
        doc.text(margenizquierdo + rec2, ini, datos[10]);

        ini = ini + 30;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}



function impresion_certificado_cemadec(datos) {
    // console.log(datos);
    // return;
    // [
    //     [$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo,($_SESSION['nombre']),($_SESSION['apellido']) ],
    //      $array,
    //      $confirma
    // ]
    alert("Impresion de certificado");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };

    // console.log(datos)
    // return;
    var doc = new jsPDF(opciones);
    doc.setFontSize(13);

    doc.setLineWidth(1.5); // Grosor de la línea
    var margenIzquierdo = 20; // Margen izquierdo en mm
    var margenDerecho = 15; // Margen derecho en mm
    var anchoPagina = doc.internal.pageSize.width;
    var xInicio = margenIzquierdo;
    var xFinal = anchoPagina - margenDerecho;
    let intmonto = Number(datos[0][7]).toLocaleString('en-US');
    var vnum = parseFloat(datos[0][11]);
    var vdecimal = isNaN(vnum) ? '' : vnum.toFixed(2);
    var vint = parseFloat(datos[0][12]);
    var vinteres = isNaN(vint) ? '' : vint.toFixed(2);
    var vipf = parseFloat(datos[0][13]);
    var intere = parseFloat(datos[0][11]);
    var ipdf = isNaN(vipf) ? '' : vipf.toFixed(2);
    var fechaini = conviertefecha(datos[0][9]);
    var fechafin = conviertefecha(datos[0][10]);

    const y = 80;
    const x = 10;
    //texto en negrita
    doc.setFontSize(11);
    doc.setFont('helvetica', 'normal');
    doc.text(x + 30, y + 12  + 8, 'Nombre: ' + datos[0][1]);
    doc.text(x + 130, y + 12 + 8, 'Cuenta No. ' + datos[0][2]);
    doc.text(x + 120, y + 16 + 8, "DPI: " + datos[0][4]);
    doc.text(x + 160, y + 16 + 8, 'Tel: ' + datos[0][5]);

    doc.text(x + 30, y + 16 + 8, 'Dirección: ' + datos[0][3]);
    doc.text(x + 145, y + 20 + 8, 'Monto: ' + 'Q.' + intmonto);
    doc.text(x + 30, y + 20 + 8, 'Monto en letras: ' + datos[0][6]);

    doc.text(x + 30, y + 24 + 8, 'A Plazo ' + datos[0][8] + ' días.');
    doc.text(x + 110, y + 28 + 8, 'Interes: ' + intere + '%' + ' Anual');
    doc.text(x + 60, y + 24 + 8, 'Fecha de Apertura: ' + fechaini);
    doc.text(x + 30, y + 28 + 8, 'Fecha de Vencimiento: ' + fechafin);

    // Definir tu párrafo

    doc.setFontSize(11);
    doc.setFont('helvetica', 'normal');
    let parrafo1 = 'La Asociación CENTRO MAYA PARA EL DESARROLLO COMUNAL "CEMADEC", CERTIFICA: Que ha recibido el ahorro a plazo fijo y entregara al asiociado a partir del día hábil siguiente a la fecha de su vencimiento, la cantidad depositada y los interes devengados.';
    // Ancho
    let anchoTexto = 155;
    let linea1 = doc.splitTextToSize(parrafo1, anchoTexto);
    // let linea2 = doc.splitTextToSize(parrafo2, anchoTexto);

    doc.text(linea1, x + 30, y - 1, { align: "justify" });
    // doc.text(linea2, x + 30, y + 62, { align: "justify" });

    doc.text(x + 55, y + 150, "         San Juan la Laguna, Sololá " + datos[0][15]); //Fecha de impresion
    // doc.text(x + 30, y + 150, "F.____________________________");
    // doc.text(x + 40, y + 154, "Firma del Asociado");
    // doc.text(x + 120, y + 154, "Representante Legal de la Cooperativa");
    // doc.text(x + 120, y + 150, "F._____________________________");

    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    let direccion = "";
    const max_caracteres = 10;
    // console.log(datos[1]);
    for (let i = 1; i < datos[1].length; i++) {
        beneficiarios = beneficiarios + datos[1][i]['nombre'];
        dpi = dpi + datos[1][i]['dpi'];
        decripc = decripc + datos[1][i]['codparent'];
        tel = tel + datos[1][i]['telefono'];
        direccion = direccion + datos[1][i]['direccion'];
        if (i !== ((datos[1].length) - 1)) {
            beneficiarios = beneficiarios + "; ";
            dpi = dpi + "; ";
            decripc = decripc + "; ";
            tel = tel + "; ";
            direccion = direccion + "; ";
        }

    }
    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    var dpi2 = doc.splitTextToSize(dpi, 180);
    var decripc2 = doc.splitTextToSize(decripc, 180);
    var tel2 = doc.splitTextToSize(tel, 180);
    var direccion2 = doc.splitTextToSize(direccion, 180);

    console.log(datos[1].length)

    if (datos[1].length > 0) {
        doc.setFontSize(10);
        doc.setFont('helvetica', 'normal');
        doc.text(x + 30, y + 36 + 8, 'BENEFICIARIOS(A):');
        doc.text(x + 30, y + 40 + 8, 'Nombre (s):  ' + splitTitle);
        doc.text(x + 30, y + 52 + 8, 'Parentesco:  ' + decripc2);
        doc.text(x + 30, y + 44 + 8, 'No. DPI:  ' + dpi2);
        doc.text(x + 30, y + 48 + 8, 'Dirección:  ' + direccion2);
        // doc.text(175,y+ 40, 'NO. TELEFONO');
        doc.setFont('helvetica', 'normal');

    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}



function impresion_libreta_cemadec(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log("impresion libreta main")
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
    //var i = 0;
    var i = posini;
    var tiptr;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var cnumdoc
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        // console.log(datos[1][i]);
        doc.setFontSize(9);
        cnumdoc = datos[1][i]['cnumdoc'];
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(12, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];

        //*** */
        if (tiptr == "R") {
            var cont = parseInt(datos[1][i]['numlinea']);
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = cnumdoc; // retiro/debito

            doc.text(8, pos, String(cont));
            doc.text(35, pos, transaccion);
            doc.text(85, pos, "" + pad(currency(monto)));
            doc.text(60, pos, "");
        }
        if (tiptr == "D") {
            var cont = parseInt(datos[1][i]['numlinea']);
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = cnumdoc; // deposito/credito

            doc.text(8, pos, String(cont));
            doc.text(35, pos, transaccion);
            doc.text(85, pos, "");
            doc.text(60, pos, "" + pad(currency(monto)));
        }
        doc.text(110, pos, "" + pad(currency(datos[1][i]['saldo'])));
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


function impresion_liquidacion_certificado_emprendedor(datos) {
    // console.log(datos);
    alert('Insertar hoja de certificado')
    var opciones = {
        orientation: 'P',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);
    var i = 1;
    var ini = 30;

    while (i < 2) {
        doc.text(10, ini, 'Certificado. ' + datos[2]);
        doc.text(97, ini, 'Fecha. ' + datos[3]);
        doc.text(145, ini, 'Codigo de cuenta: ' + datos[4]);

        ini = ini + 10;
        doc.text(10, ini, 'Cliente: ' + datos[5]);

        ini = ini + 10;
        doc.text(10, ini, 'Monto apertura: ' + datos[6]);
        doc.text(130, ini, 'Interes: Q. ' + parseFloat(datos[7]).toFixed(2));

        ini = ini + 12;
        doc.text(10, ini, 'ISR: ' + parseFloat(datos[8]).toFixed(2));
        ini = ini + 10;
        doc.text(10, ini, 'Monto en letras: ' + datos[9]);
        ini = ini + 10;
        doc.text(10, ini, 'Recibo: ' + datos[10]);
        ini = ini + 60;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}