function printdiv(condi, idiv, dir, xtra) {
    loaderefect(1);
    dire = "views/migrations/views001.php";
    $.ajax({
        url: dire, method: "POST", data: { condi, xtra },
        success: function (data) {
            loaderefect(0);
            $(idiv).html(data);
        }
    })
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
    i = 0;
    while (i < datos.length) {
        var e = document.getElementById(datos[i]);
        selects2[datos[i]] = e.options[e.selectedIndex].value;
        i++;
    }
    return selects2;
}
function getradiosval(datos) {
    const radios2 = {};
    i = 0;
    while (i < datos.length) {
        radios2[datos[i]] = document.querySelector('input[name="' + datos[i] + '"]:checked').value;
        i++;
    }
    return radios2;
}
function process(tipo, filemigration) {
    shutdownloader(0);
    const migrationCode = document.getElementById('migrationCode').value;
    if (!migrationCode) {
        alert('Por favor, ingrese un código de migración antes de continuar.');
        shutdownloader(1);
        return;
    }

    const formData = new FormData();
    formData.append('migrationCode', migrationCode);
    formData.append('tipo', tipo);

    // Enviamos el código de migración
    fetch('views/migrations/migrations/' + filemigration + '.php', {
        method: 'POST',
        body: formData
    }).then(response => {
        if (!response.ok) {
            throw new Error('Error al iniciar el proceso.');
        }
        return response.text();
    }).then(text => {
        console.log("Respuesta del servidor:", text);
        // Si el proceso fue exitoso, iniciamos el SSE
        listenForProgress(filemigration);
    }).catch(error => {
        console.error(error);
        document.getElementById("progress").innerHTML += `<p style="color: red;">Error: ${error.message}</p>`;
    });
}

function loadfilecompressed(tipo, filemigration, datos = [
    [],
    [],
    [],
    []
]) {
    shutdownloader(0);
    const fileInput = document.getElementById('excelFile');
    if (!fileInput.files || fileInput.files.length === 0) {
        alert('Por favor, seleccione un archivo antes de continuar.');
        shutdownloader(1);
        return;
    }

    const formData = new FormData();
    formData.append('excelFile', fileInput.files[0]);
    formData.append('tipo', tipo);
    formData.append('inputs', JSON.stringify(getinputsval(datos[0])));
    formData.append('selects', JSON.stringify(getselectsval(datos[1])));
    formData.append('radios', JSON.stringify(getradiosval(datos[2])));
    formData.append('archivo', JSON.stringify(datos[3]));

    // Primero, enviamos el archivo
    fetch('views/migrations/migrations/' + filemigration + '.php', {
        method: 'POST',
        body: formData
    }).then(response => {
        if (!response.ok) {
            throw new Error('Error al iniciar la carga.');
        }
        return response.text();
    }).then(text => {
        console.log("Respuesta del servidor:", text);
        // Si la carga fue exitosa, iniciamos el SSE
        listenForProgress(filemigration);
    }).catch(error => {
        console.error(error);
        document.getElementById("progress").innerHTML += `<p style="color: red;">Error: ${error.message}</p>`;
    });
}

function shutdownloader(op) {
    const button = document.getElementById('migrarBtn');
    const button2 = document.getElementById('migrarBtn2');
    const spinner = document.getElementById('spinner');
    if (op == 1) {
        button.disabled = false;
        button2.disabled = false;
        spinner.style.display = 'none';
    } else {
        button.disabled = true;
        button2.disabled = true;
        spinner.style.display = 'block';
    }
}