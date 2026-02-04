<?php
//PARA LOS MENUS GENERALES, 
include '../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
$condi = $_POST["condi"];

switch ($condi) {
  /*----------------------- PARA MOSTRAR LOS MUNICIPIOS QUE SE TIENEN  ---------------------------------------------------------- */
  case 'departa':
    $depa = $_POST["iddepa"];
    $muni = mysqli_query($conexion, "SELECT nombre,codigo FROM `tb_municipios` WHERE id_departamento = $depa");

    while ($municipalidad = mysqli_fetch_array($muni, MYSQLI_ASSOC)) {
      $nombre = ($municipalidad["nombre"]);
      $codigo_municipio = ($municipalidad["codigo"]);
      echo '<option value="' . $codigo_municipio . '">' . $nombre . '</option>';
    }
    break;

  /*------------- PARA OBNETER LOS DATOS DE LAS ACTIVIDADES/SECTOR ECONOMICO ---------------*/
  case 'SctrEcono':
    $dtass = $_POST["dtass"];
    $consulta = mysqli_query($general, "SELECT id_ActiEcono, Titulo FROM `tb_ActiEcono` WHERE Id_SctrEcono = '$dtass'");
    echo '<option value="0" selected disabled>Seleccione Actividad Economica</option>';
    while ($dtsgen = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
      $idAct = $dtsgen["id_ActiEcono"];
      $Titulo = $dtsgen["Titulo"];
      echo '<option value="' . $idAct . '">' . $Titulo . '</option>';
    }
    mysqli_close($general);
    break;

  /* BENITO HAY ME EXPLICAS QUE ONDA CON ESTO   */
  case 'actEcon2':
    $sector = $_POST["idsector"];
    $sectorE = mysqli_query($conexion, "SELECT * FROM `tb_sececonom` WHERE id_SecEconom = $sector");
    while ($sec = mysqli_fetch_array($sectorE, MYSQLI_ASSOC)) {
      $name = ($sec["SecEconom"]);
      $codigo = ($sec["id_SecEconom"]);
      echo '<option value="' . $codigo . '">' . $name . '</option>';
    }
    break;

  case 'actEcon':
    $ida = $_POST["idsector"];
    $sectorE = mysqli_query($conexion, "SELECT tb_sececonom.id_SecEconom, tb_sececonom.SecEconom FROM tb_sececonom INNER JOIN tb_activeconom ON tb_sececonom.id_SecEconom=tb_activeconom.id_SecEconom  WHERE tb_activeconom.id_ActivEcom = $ida");
    while ($sec = mysqli_fetch_array($sectorE, MYSQLI_ASSOC)) {
      $name = ($sec["SecEconom"]);
      $codigo = ($sec["id_SecEconom"]);
      echo '<option value="' . $codigo . '">' . $name . '</option>';
    }
    break;
}
