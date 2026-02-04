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


//version actual
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
    grecaptcha.ready(function () {
        grecaptcha.execute('6Ld1-g0qAAAAAHmikE5cc8FL3ctmtjayshUNAbv8', { action: 'submit' }).then(function (token) {
            if (!validateForm()) {
                loaderefect(0);
                Swal.fire({
                    icon: 'error',
                    title: '¡ERROR!',
                    text: "Rellene todos los campos obligatorios"
                });
                return;
            }

            // if (validarFormLogin()) {
            // Serializa el formulario y añade el tokenrecaptcha
            var dataForm = $form.serialize() + '&token=' + token;
            $.ajax({
                type: 'POST',
                url: 'src/cruds/crud_usuario.php',
                data: dataForm,
                dataType: 'json',
                beforeSend: function () {
                    
                },
                success: function (data) {
                    icono = ("icon" in data) ? data.icon : 'error';
                    titulo = ("title" in data) ? data.title : '¡ERROR!';
                    // console.log(data)
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
                    // console.log(xhr)
                    loaderefect(0);
                    Swal.fire({
                        icon: 'error',
                        title: '¡ERROR!',
                        text: 'Codigo de error: ' + xhr.status + ', Información de error: ' + xhr.responseJSON
                    });
                },
                complete: function (dat) {
                    // console.log(dat)
                    loaderefect(0);
                },
            });
            // }
        });
    });
});

$("#eliminarsesion").click(function (e) {
    // console.log('ci');
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
            // console.log(data);
        },
        error: function (xhr) {
            loaderefect(0);
            Swal.fire({
                icon: 'error',
                title: '¡ERROR!',
                text: 'Codigo de error: ' + xhr.status + ', Información de error: ' + xhr.responseJSON
            });
        },
        complete: function () {
            loaderefect(0);
        },
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