<?php
session_start();
include '../../../../../includes/BD_con/db_con.php';

mysqli_set_charset($conexion, 'utf8');
date_default_timezone_set('America/Guatemala');
$condi = $_POST["condi"];
$codusu = $_SESSION['id'];

switch ($condi) {
    case 'no_recibo': {
            $id = $_POST["xtra"];
            // print_r($id);
?>

<!-- Crud para agregar, editar y eliminar tipo de gastos  -->
<input type="text" id="file" value="creditos_01" style="display: none;">
<input type="text" id="condi" value="gastos" style="display: none;">
<div class="text" style="text-align:center">Configuraciones POS</div>

<style>
.switch {
    font-size: 17px;
    position: relative;
    display: inline-block;
    width: 3.5em;
    height: 2em;
}
.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgb(182, 182, 182);
    transition: .4s;
    border-radius: 10px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 1.4em;
    width: 1.4em;
    border-radius: 8px;
    left: 0.3em;
    bottom: 0.3em;
    transform: rotate(270deg);
    background-color: rgb(255, 255, 255);
    transition: .4s;
}

.switch input:checked+.slider {
    background-color: #21cc4c;
}

.switch input:focus+.slider {
    box-shadow: 0 0 1px #2196F3;
}

.switch input:checked+.slider:before {
    transform: translateX(1.5em);
}

.card-header {
    display: flex;
    align-items: center;
    font-size: 1.25rem;
    /* Ajusta el tamaño del texto según sea necesario */
    color: #333;
    /* Color del texto */
}

.card-header svg {
    width: 24px;
    height: 24px;
    margin-left: 8px;
    fill: #333;
}
</style>

<?php
// Consultar el estado de la configuración
$configName = "check_recibo";
$sql = "SELECT estado FROM tb_configCre WHERE config_name = '$configName'";
$result = $conexion->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $estado = $row["estado"];
} else {
    $estado = 0;
}
?>
<div class="card">
    <div class="card-header">
        Configuraciónes
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path
                d="M19.14 12.936c.014-.305.02-.615.02-.936s-.007-.63-.02-.936l2.11-1.65c.192-.15.247-.42.12-.64l-2-3.464a.494.494 0 0 0-.592-.22l-2.49 1a8.77 8.77 0 0 0-1.616-.936l-.38-2.65a.502.502 0 0 0-.496-.42h-4a.502.502 0 0 0-.497.42l-.38 2.65a8.767 8.767 0 0 0-1.615.936l-2.491-1a.498.498 0 0 0-.592.22l-2 3.464a.504.504 0 0 0 .12.64l2.11 1.65c-.014.306-.02.616-.02.936s.007.63.02.936l-2.11 1.65a.504.504 0 0 0-.12.64l2 3.464c.136.23.43.31.68.22l2.49-1c.51.38 1.05.7 1.615.936l.38 2.65a.502.502 0 0 0 .497.42h4c.246 0 .455-.177.496-.42l.38-2.65c.566-.236 1.106-.556 1.616-.936l2.49 1c.25.09.545.01.68-.22l2-3.464a.504.504 0 0 0-.12-.64l-2.11-1.65zM12 15.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7z" />
        </svg>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <div class="row g-3">
                <div class="card text-center">
                </div>
                <div class="card-body" style="width: 80%;">
                    <h5 class="card-title">No. de recibo Automatico</h5>
                    <div style="display: flex; align-items: center;">
                        <p class="card-text" style="width: 80%; flex: 1;">Esta opción permitirá activar o desactivar el
                            campo de número de recibo en el POS para que se genere automáticamente.</p>
                        <label class="switch" style="margin-left: 10px;">
                            <input type="checkbox" id="check_recibo" <?php echo ($estado == 1) ? "checked" : ""; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $configName2 = "check_look_recibo"; // Nombre de la segunda configuración
        $sql2 = "SELECT estado FROM tb_configCre WHERE config_name = '$configName2'";
        $result2 = $conexion->query($sql2);
        if ($result2->num_rows > 0) {
            $row2 = $result2->fetch_assoc();
            $estado2 = $row2["estado"];
        } else { 
            $estado2 = 0;
        }
        ?>
        <div class="mb-3">
            <div class="row g-3">
                <div class="card text-center">
                </div>
                <div class="card-body" style="width: 80%;">
                    <h5 class="card-title">Editar No. Recibo</h5>
                    <div style="display: flex; align-items: center;">
                        <p class="card-text" style="width: 80%; flex: 1;">Bloquear Edición del Número de Recibo</p>
                        <label class="switch" style="margin-left: 10px;">
                            <input type="checkbox" id="check_look_recibo"
                                <?php echo ($estado2 == 1) ? "checked" : ""; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $configName2 = "check_fecha"; // Nombre de la segunda configuración
        $sql3 = "SELECT estado FROM tb_configCre WHERE config_name = '$configName2'";
        $result3 = $conexion->query($sql3);
        if ($result3->num_rows > 0) {
            $row2 = $result3->fetch_assoc();
            $estado3 = $row2["estado"];
        } else { 
            $estado3 = 0;
        }
        ?>
        <div class="mb-3">
            <div class="row g-3">
                <div class="card text-center">
                </div>
                <div class="card-body" style="width: 80%;">
                    <h5 class="card-title">Editar Fecha</h5>
                    <div style="display: flex; align-items: center;">
                        <p class="card-text" style="width: 80%; flex: 1;">Bloquear Edición de la fecha</p>
                        <label class="switch" style="margin-left: 10px;">
                            <input type="checkbox" id="check_fecha" <?php echo ($estado3 == 1) ? "checked" : ""; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $configName2 = "check_capital"; // Nombre de la segunda configuración
        $sql4 = "SELECT estado FROM tb_configCre WHERE config_name = '$configName2'";
        $result4 = $conexion->query($sql4);
        if ($result4->num_rows > 0) {
            $row2 = $result4->fetch_assoc();
            $estado4 = $row2["estado"];
        } else { 
            $estado4 = 0;
        }
        ?>
        <div class="mb-3">
            <div class="row g-3">
                <div class="card text-center">
                </div>
                <div class="card-body" style="width: 80%;">
                    <h5 class="card-title">Editar Capital</h5>
                    <div style="display: flex; align-items: center;">
                        <p class="card-text" style="width: 80%; flex: 1;">Bloquear Edición de Capital</p>
                        <label class="switch" style="margin-left: 10px;">
                            <input type="checkbox" id="check_capital" <?php echo ($estado4 == 1) ? "checked" : ""; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $configName2 = "check_interes"; 
        $sql5 = "SELECT estado FROM tb_configCre WHERE config_name = '$configName2'";
        $result5 = $conexion->query($sql5);
        if ($result5->num_rows > 0) {
            $row2 = $result5->fetch_assoc();
            $estado5 = $row2["estado"];
        } else { 
            $estado5 = 0;
        }
        ?>
        <div class="mb-3">
            <div class="row g-3">
                <div class="card text-center">
                </div>
                <div class="card-body" style="width: 80%;">
                    <h5 class="card-title">Editar Interés</h5>
                    <div style="display: flex; align-items: center;">
                        <p class="card-text" style="width: 80%; flex: 1;">Bloquear Edición de Interés</p>
                        <label class="switch" style="margin-left: 10px;">
                            <input type="checkbox" id="check_interes" <?php echo ($estado5 == 1) ? "checked" : ""; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $configName2 = "check_mora"; 
        $sql6 = "SELECT estado FROM tb_configCre WHERE config_name = '$configName2'";
        $result6 = $conexion->query($sql6);
        if ($result6->num_rows > 0) {
            $row2 = $result6->fetch_assoc();
            $estado6 = $row2["estado"];
        } else { 
            $estado6 = 0;
        }
        ?>
        <div class="mb-3">
            <div class="row g-3">
                <div class="card text-center">
                </div>
                <div class="card-body" style="width: 80%;">
                    <h5 class="card-title">Editar Mora</h5>
                    <div style="display: flex; align-items: center;">
                        <p class="card-text" style="width: 50%; flex: 1;">Bloquear Edición de Mora</p>
                        <label class="switch" style="margin-left: 10px;">
                            <input type="checkbox" id="check_mora" <?php echo ($estado6 == 1) ? "checked" : ""; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $configName2 = "check_otros"; 
        $sql7 = "SELECT estado FROM tb_configCre WHERE config_name = '$configName2'";
        $result7= $conexion->query($sql7);
        if ($result7->num_rows > 0) {
            $row2 = $result7->fetch_assoc();
            $estado7 = $row2["estado"];
        } else { 
            $estado7 = 0;
        }
        ?>
        <div class="mb-3">
            <div class="row g-3">
                <div class="card text-center">
                </div>
                <div class="card-body" style="width: 80%;">
                    <h5 class="card-title">Editar Otros</h5>
                    <div style="display: flex; align-items: center;">
                        <p class="card-text" style="width: 50%; flex: 1;">Bloquear Edición de Otros</p>
                        <label class="switch" style="margin-left: 10px;">
                            <input type="checkbox" id="check_otros" <?php echo ($estado7 == 1) ? "checked" : ""; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<script>
//primer boton
document.getElementById('check_recibo').addEventListener('change', function() {
    let estado = this.checked ? 1 : 0;
    let xhr = new XMLHttpRequest();
    xhr.open("POST", "../../src/cruds/crud_admincre.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            console.log(xhr.responseText);
        }
    };
    xhr.send("condi=update_auto_no_recib&config_name=check_recibo&estado=" + estado);
});
//segundo boton 
document.getElementById('check_look_recibo').addEventListener('change', function() {
    let estado = this.checked ? 1 : 0;
    let xhr = new XMLHttpRequest();
    xhr.open("POST", "../../src/cruds/crud_admincre.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            console.log(xhr.responseText);
        }
    };
    xhr.send("condi=update_check_no_recibo&config_name=check_look_recibo&estado=" + estado);
});
//tercer boton 
document.getElementById('check_fecha').addEventListener('change', function() {
    let estado = this.checked ? 1 : 0;
    let xhr = new XMLHttpRequest();
    xhr.open("POST", "../../src/cruds/crud_admincre.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            console.log(xhr.responseText);
        }
    };
    xhr.send("condi=update_check_fecha&config_name=check_fecha&estado=" + estado);
});
//cuarto boton 
document.getElementById('check_capital').addEventListener('change', function() {
    let estado = this.checked ? 1 : 0;
    let xhr = new XMLHttpRequest();
    xhr.open("POST", "../../src/cruds/crud_admincre.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            console.log(xhr.responseText);
        }
    };
    xhr.send("condi=update_check_capital&config_name=check_capital&estado=" + estado);
});
//quinto boton
document.getElementById('check_interes').addEventListener('change', function() {
    let estado = this.checked ? 1 : 0;
    let xhr = new XMLHttpRequest();
    xhr.open("POST", "../../src/cruds/crud_admincre.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            console.log(xhr.responseText);
        }
    };
    xhr.send("condi=update_check_interes&config_name=check_interes&estado=" + estado);
});
//sexto boton
document.getElementById('check_mora').addEventListener('change', function() {
    let estado = this.checked ? 1 : 0;
    let xhr = new XMLHttpRequest();
    xhr.open("POST", "../../src/cruds/crud_admincre.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            console.log(xhr.responseText);
        }
    };
    xhr.send("condi=update_check_mora&config_name=check_mora&estado=" + estado);
});
//septimo boton
document.getElementById('check_otros').addEventListener('change', function() {
    let estado = this.checked ? 1 : 0;
    let xhr = new XMLHttpRequest();
    xhr.open("POST", "../../src/cruds/crud_admincre.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            console.log(xhr.responseText);
        }
    };
    xhr.send("condi=update_check_otros&config_name=check_otros&estado=" + estado);
});
</script>
<?php
        }
        break;
}
    ?>