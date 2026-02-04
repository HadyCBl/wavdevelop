function loaderefect(sh) {
    const LOADING = document.querySelector('.loader-container');
    if (!LOADING) return;
    
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

$(document).on("click", "#togglePassword", function (e) {
    e.preventDefault();
    var type = $(this).parent().parent().find("#password").attr("type");
    if (type == "password") {
        $(this).removeClass("fa-regular fa-eye");
        $(this).addClass("fa-regular fa-eye-slash");
        $(this).parent().parent().find("#password").attr("type", "text");
    } else if (type == "text") {
        $(this).removeClass("fa-regular fa-eye-slash");
        $(this).addClass("fa-regular fa-eye");
        $(this).parent().parent().find("#password").attr("type", "password");
    }
});

$(document).on("click", "#togglePasswordindex", function (e) {
    e.preventDefault();
    
    let passwordField = $("#password");  
    let eyeIcon = $("#eyeIcon");  
    
    if (passwordField.attr("type") === "password") {
        passwordField.attr("type", "text");  
        eyeIcon.removeClass("fa-eye").addClass("fa-eye-slash");  
    } else {
        passwordField.attr("type", "password");  
        eyeIcon.removeClass("fa-eye-slash").addClass("fa-eye");  
    }
});

$("#frmlogin").on('submit', function (e) {
    e.preventDefault();
    var $form = $(this);
    loaderefect(1);
    
    // VALIDACIÓN SIMPLE - SIN reCAPTCHA
    if (!validateForm()) {
        loaderefect(0);
        Swal.fire({
            icon: 'error',
            title: '¡ERROR!',
            text: "Rellene todos los campos obligatorios"
        });
        return;
    }

    var dataForm = $form.serialize();
    
    $.ajax({
        type: 'POST',
        url: 'src/cruds/crud_usuario.php',
        data: dataForm,
        dataType: 'json',
        success: function (data) {
            icono = ("icon" in data) ? data.icon : 'error';
            titulo = ("title" in data) ? data.title : '¡ERROR!';
            if (data[0]) {
                location.reload();
            } else {
                loaderefect(0);
                Swal.fire({
                    icon: icono,
                    title: titulo,
                    text: data[1]
                });
                setTimeout(function () {
                    location.reload();
                }, 1500);
            }
        },
        error: function (xhr) {
            loaderefect(0);
            Swal.fire({
                icon: 'error',
                title: '¡ERROR!',
                text: 'Error: ' + (xhr.responseJSON || xhr.statusText || 'Error desconocido')
            });
        },
        complete: function () {
            loaderefect(0);
        },
    });
});

$("#eliminarsesion").click(function (e) {
    e.preventDefault();
    $.ajax({
        type: 'POST',
        url: 'src/cruds/crud_usuario.php',
        data: { 'condi': 'salir' },
        dataType: 'json',
        beforeSend: function () {
            loaderefect(1);
        },
        success: function (data) {
            loaderefect(0);
            location.reload();
        },
        error: function (xhr) {
            loaderefect(0);
            Swal.fire({
                icon: 'error',
                title: '¡ERROR!',
                text: 'Error al cerrar sesión'
            });
        }
    });
});

function validateForm() {
    const fields = document.querySelectorAll('#cuadro [required]');
    let isValid = true;

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