<?php
return; // evitar ejecucion directa
// $modo = isset($argv[1]) ? $argv[1] : 'default';

// echo "Modo de ejecución: " . $modo . "\n";

// date_default_timezone_set('America/Guatemala');
// $hoy2 = date("Y-m-d H:i:s");

// echo "fecha hora actual: " . $hoy2 . "\n";
// $bases_url = [
//     // BANA1
//     'https://crediprendas.sotecprotech.com/',
//     'https://fape.sotecprotech.com/',
//     'https://coopim.sotecprotech.com/',
//     'https://guatepresta.sotecprotech.com/',
//     'https://multinorte.sotecprotech.com/',
//     'https://vidanueva.sotecprotech.com/',
//     'https://demo.sotecprotech.com/',
//     'https://tecpan.sotecprotech.com/',
//     // BANA2
//     'https://adg.microsystemplus2.com/',
//     'https://altascumbres.microsystemplus2.com/',
//     'https://coditoto.microsystemplus2.com/',
//     'https://coopeadg.microsystemplus2.com/',
//     'https://coopibelen.microsystemplus2.com/',
//     'https://cooprode.microsystemplus2.com/',
//     'https://credimarq.microsystemplus2.com/',
//     'https://credireforma.microsystemplus2.com/',
//     'https://demo.microsystemplus2.com/',
//     'https://mayaland.microsystemplus2.com/',
//     'https://mueblex.microsystemplus2.com/',
//     // BANA3
//     'https://ammi.microsystemplus2.com/',
//     'https://coopeixil.microsystemplus2.com/',
//     'https://coopeplus.microsystemplus2.com/',
//     'https://coopemujer.microsystemplus2.com/',
//     'https://cope27.microsystemplus2.com/',
//     'https://copetikal.microsystemplus2.com/',
//     'https://coinco.microsystemplus2.com/',
//     'https://djd.microsystemplus2.com/',
//     'https://coopedjd.microsystemplus2.com/',
//     'https://dynamics.microsystemplus2.com/',
//     'https://otziles.microsystemplus2.com/',
//     // BANA4
//     'https://codepa.microsystemplus2.com/',
//     'https://digital.microsystemplus2.com/',
//     'https://fefran.microsystemplus2.com/',
//     'https://kumool.microsystemplus2.com/',
//     'https://lochbalib.microsystemplus2.com/',
//     'https://miempregua.microsystemplus2.com/',
//     //BANA5
//     'https://alfinsa.microsystemplus3.com/',
//     'https://credivasquez.microsystemplus3.com/',
//     'https://jireh.microsystemplus3.com/',
//     //continuara...

//     // AMZ1
//     'https://aliba.microsystemplus.com/',
//     'https://ciacreho.microsystemplus.com/',
//     'https://crediapoyemos.microsystemplus.com/',
//     'https://ifiisa.microsystemplus.com/',
//     'https://nawal.microsystemplus.com/',
//     'https://primavera.microsystemplus.com/',
//     'https://sendero.microsystemplus.com/',
//     // AMZ2
//     'https://adif.amzmicrosystemplus.com/',
//     'https://cicre.amzmicrosystemplus.com/',
//     'https://copefuente.amzmicrosystemplus.com/',
//     'https://coopeadif.amzmicrosystemplus.com/',
//     'https://corpocredit.amzmicrosystemplus.com/',
//     'https://credimass.amzmicrosystemplus.com/',
//     'https://credisa.amzmicrosystemplus.com/',
//     'https://prendaya.amzmicrosystemplus.com/',
// ];
// $bases_url = [
//     // BANA1
//     'http://localhost:3000/',
//     'https://pruebas.dominio.com/',

// ];

// $files = [
//     'calculo_mora.php',
//     'inicio_de_mes.php'
// ];
// $i = 0;
// while ($i < count($bases_url)) {
//     echo '\n INSTITUCION: ' . $bases_url[$i] . '\n';
//     foreach ($files as $file) {
//         $retorno = executescript($bases_url[$i] . 'src/funcphp/cron_tasks/' . $file);
//         //eval($retorno);
//         if ($retorno === false) {
//             echo "Error al obtener la URL.\n";
//         } else {
//             echo "Contenido obtenido: " . $retorno . "\n";
//         }
//     }
//     $i++;
// }

// function executescript($base_url)
// {
//     //$script_content = file_get_contents($base_url, false, $context);
//     // $script_content = curl_get_file_contents($base_url);
//     $c = curl_init();
//     curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
//     curl_setopt($c, CURLOPT_URL, $base_url);
//     $contents = curl_exec($c);
//     curl_close($c);

//     if ($contents) return $contents;
//     else return FALSE;
// }
