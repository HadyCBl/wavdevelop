<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
date_default_timezone_set('America/Guatemala');
$condi = $_POST['condi'];
$codusu = $_SESSION['id'];
$id_agencia = $_SESSION['id_agencia'];
$codagencia = $_SESSION['agencia'];
switch ($condi) {
	case 'reporte_buro': {
			$xtra = $_POST["xtra"];
?>
			<input type="text" id="file" value="creditos_03" style="display: none;">
			<input type="text" id="condi" value="reporte_buro" style="display: none;">


			<br>
			<!-- negroy prueba de  -->
			<div class="card">
				<div class="card-header">FILTROS</div>
				<div class="card-body">
					<div class="row container contenedort">
						<div class="col-sm-12">
							<div class="row">
								<div class="col-sm-4">
									<label for=""> MES </label>
									<select id="mesSelect" class="form-select" onchange="cargarAnios()">
										<option selected disabled value="0">-Seleccione un mes-</option>
										<option value="01">Enero</option>
										<option value="02">Febrero</option>
										<option value="03">Marzo</option>
										<option value="04">Abril</option>
										<option value="05">Mayo</option>
										<option value="06">Junio</option>
										<option value="07">Julio</option>
										<option value="08">Agosto</option>
										<option value="09">Septiembre</option>
										<option value="10">Octubre</option>
										<option value="11">Noviembre</option>
										<option value="12">Diciembre</option>
									</select>
								</div>
								<div class="col-sm-4">
									<label for=""> AÑO </label>
									<select id="yearSelect" class="form-select" onchange="slctActi()">
										<option selected value="0">-Seleccione un año-</option>
									</select>
								</div>
							</div>
							<br>
							<div class="row">
								<div class="col-sm-12">
									<button type="button" class="btn btn-outline-danger" id="btngen" title="Generar archivo de texto" onclick="validarFecha()" disabled> <i class="fa-solid fa-file-pdf"> </i> Generar </button>
									<button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
										<i class="fa-solid fa-ban"></i> Cancelar </button>
									<button type="button" class="btn btn-outline-warning" onclick="salir()">
										<i class="fa-solid fa-circle-xmark"></i> Salir </button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
<?php }
		break;
}

?>