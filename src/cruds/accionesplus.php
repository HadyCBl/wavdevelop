<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  include __DIR__ . '/../../includes/Config/config.php';
  header('location: ' . BASE_URL . '404.php');
}
session_start();

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

$csrf = new CSRFProtection();
$secureID = new SecureID($key1);
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

use Micro\Helpers\Log;
use App\Generic\FileProcessor;
use App\Generic\Agencia;
use App\Generic\Models\ClienteJsonService;

include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');

$idusuario = $_SESSION['id'];

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
$idagencia = $_SESSION['id_agencia'];


$accion = $_POST["accion"] ?? $_POST["condi"] ?? '';

switch ($accion) {
  case 'cargar_imagen':
    // ... código existente para cargar imagen ...
    break;

  // ========================================
  // ⭐⭐⭐ MÉTODOS CRUD INFO ADICIONAL ⭐⭐⭐
  // ========================================

  case 'descargar_archivo':
    // ✅ DESCARGAR ARCHIVO DIRECTAMENTE
    $archivo_id = isset($_GET['archivo_id']) ? intval($_GET['archivo_id']) : 0;
    $path_file = isset($_GET['path']) ? $_GET['path'] : '';

    if ($archivo_id > 0 && !empty($path_file)) {
      // Verificar que el archivo existe usando FileProcessor
      $fileProcessor = new FileProcessor(__DIR__ . '/../../../');

      $rutas_a_probar = [
        $path_file,  // Ruta original de BD
        'imgcoope.microsystemplus.com/' . str_replace('imgcoope.microsystemplus.com/', '', $path_file),
        __DIR__ . '/../../../' . $path_file
      ];

      $archivo_encontrado = false;
      $ruta_final = '';

      foreach ($rutas_a_probar as $ruta) {
        if ($fileProcessor->fileExists($ruta)) {
          $archivo_encontrado = true;
          $ruta_final = $ruta;
          break;
        }
      }

      if ($archivo_encontrado) {
        $full_path = $fileProcessor->getFullPath($ruta_final);

        if (file_exists($full_path)) {
          // Obtener información del archivo
          $nombre_archivo = basename($full_path);
          $mime_type = $fileProcessor->getMimeType($ruta_final);

          // Headers para descarga
          header('Content-Type: ' . $mime_type);
          header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
          header('Content-Length: ' . filesize($full_path));
          header('Cache-Control: no-cache, must-revalidate');
          header('Pragma: no-cache');

          // Leer y enviar el archivo
          readfile($full_path);
          exit;
        } else {
          error_log("❌ ERROR DESCARGA - Archivo no existe físicamente: " . $full_path);
        }
      } else {
        error_log("❌ ERROR DESCARGA - Archivo no encontrado en ninguna ruta para ID: " . $archivo_id);
      }
    }

    // Si llega aquí, hubo error
    header('HTTP/1.0 404 Not Found');
    echo "Archivo no encontrado";
    exit;
    break;

  case 'cargar_info_adicional':
    $response = [
      'message' => 'Error al cargar la información',
      'status' => '0',
      'reprint' => 0
    ];

    try {
      $database->openConnection();

      $id_adicional = $_POST['id'] ?? 0;

      if (empty($id_adicional)) {
        throw new Exception("ID de información adicional requerido");
      }

      // Obtener información adicional
      $info_adicional = $database->selectColumns(
        'cli_adicionales',
        ['id', 'entidad_tipo', 'entidad_id', 'descripcion', 'latitud', 'longitud', 'altitud', '`precision`', 'direccion_texto'],
        "id=? AND estado=1",
        [$id_adicional]
      );

      if (empty($info_adicional)) {
        throw new Exception("Registro no encontrado");
      }

      // Obtener archivos asociados
      $archivos = $database->selectColumns(
        'cli_adicional_archivos',
        ['id', 'path_file'],
        "id_adicional=?",
        [$id_adicional]
      );

      // ✅ Procesar archivos con FileProcessor y generar HTML completo
      // 🔧 FORZAR RUTA CORRECTA: 3 niveles arriba para llegar a la raíz del proyecto
      $fileProcessor = new \App\Generic\FileProcessor(__DIR__ . '/../../../');
      $archivos_procesados = [];
      $html_imagenes = '';
      $contador_imagenes = 0;

      foreach ($archivos as $archivo) {
        $path_file = $archivo['path_file'];
        $archivo_procesado = [
          'id' => $archivo['id'],
          'path_file' => $path_file,
          'filename' => basename($path_file),
          'exists' => false,
          'is_image' => false,
          'data_uri' => null,
          'html_preview' => ''
        ];

        // 🔍 Debug: Log de la ruta que se está procesando
        error_log("🔍 DEBUG FileProcessor - Procesando archivo: " . $path_file);
        error_log("🔍 DEBUG FileProcessor - Ruta base CORREGIDA: " . __DIR__ . '/../../../');
        error_log("🔍 DEBUG FileProcessor - Ruta completa esperada: " . __DIR__ . '/../../../' . $path_file);

        // 🔧 INTENTAR MÚLTIPLES FORMATOS DE RUTA
        $rutas_a_probar = [
          $path_file,  // Ruta original desde BD
          'imgcoope.microsystemplus.com/' . $path_file,  // Si no incluye el prefijo
          'imgcoope.microsystemplus.com/demo/001900500008/adicionales/' . basename($path_file)  // Ruta forzada para debug
        ];

        $archivo_encontrado = false;
        $ruta_exitosa = '';

        foreach ($rutas_a_probar as $ruta_prueba) {
          error_log("🔍 Probando ruta: " . $ruta_prueba);
          if ($fileProcessor->fileExists($ruta_prueba)) {
            error_log("✅ DEBUG FileProcessor - Archivo encontrado con ruta: " . $ruta_prueba);
            $path_file = $ruta_prueba;  // Usar la ruta que funciona
            $archivo_encontrado = true;
            $ruta_exitosa = $ruta_prueba;
            break;
          }
        }

        if ($archivo_encontrado) {
          if ($archivo_encontrado) {
            error_log("✅ DEBUG FileProcessor - Archivo existe: " . $ruta_exitosa);
            $archivo_procesado['exists'] = true;
            $archivo_procesado['is_image'] = $fileProcessor->isImage($path_file);

            if ($archivo_procesado['is_image']) {
              $fileInfo = $fileProcessor->getFileInfo($path_file);
              $archivo_procesado['data_uri'] = $fileInfo['data_uri'];

              // ✅ Generar HTML directamente como en la tabla principal
              $archivo_procesado['html_preview'] = '<img src="' . $fileInfo['data_uri'] . '" 
                                                       alt="' . htmlspecialchars($archivo_procesado['filename']) . '" 
                                                       class="img-thumbnail" 
                                                       style="width: 100%; height: 80px; object-fit: cover; cursor: pointer;" 
                                                       onclick="verImagenCompleta(\'' . $fileInfo['data_uri'] . '\')">';

              // Construir HTML para todas las imágenes
              $html_imagenes .= '<div class="col-3 mb-2">' . $archivo_procesado['html_preview'] . '</div>';
              $contador_imagenes++;

              error_log("📷 DEBUG FileProcessor - Data URI generado para: " . $path_file);
            } else {
              // ✅ ARCHIVO NO IMAGEN (PDF, DOC, etc.) - GENERAR HTML SIN DATA URI
              $iconClass = 'fa-file';
              $btnClass = 'btn-outline-secondary';
              $btnText = 'Descargar';

              if (strpos($archivo_procesado['filename'], '.pdf') !== false) {
                $iconClass = 'fa-file-pdf';
                $btnClass = 'btn-outline-danger';
                $btnText = 'Ver PDF';
              } else if (strpos($archivo_procesado['filename'], '.doc') !== false) {
                $iconClass = 'fa-file-word';
                $btnClass = 'btn-outline-primary';
              } else if (strpos($archivo_procesado['filename'], '.xls') !== false) {
                $iconClass = 'fa-file-excel';
                $btnClass = 'btn-outline-success';
              }

              // ✅ Para PDFs y otros archivos, usar función JavaScript de descarga
              $archivo_procesado['html_preview'] = '<div class="bg-light border p-2 rounded text-center" style="height: 80px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                                                    <i class="fa ' . $iconClass . ' mb-1 text-primary" style="font-size: 24px;"></i>
                                                    <small class="text-truncate" style="max-width: 100px;">' . htmlspecialchars($archivo_procesado['filename']) . '</small>
                                                    <button onclick="descargarArchivo(' . $archivo['id'] . ', \'' . htmlspecialchars($path_file) . '\', \'' . htmlspecialchars($archivo_procesado['filename']) . '\')" 
                                                            class="btn btn-xs ' . $btnClass . ' mt-1" style="font-size: 10px; padding: 2px 6px;">
                                                      <i class="fa fa-download"></i> ' . $btnText . '
                                                    </button>
                                                  </div>';

              $html_imagenes .= '<div class="col-3 mb-2">' . $archivo_procesado['html_preview'] . '</div>';
              $contador_imagenes++; // Contar también los PDFs y otros archivos

              error_log("📄 DEBUG FileProcessor - Enlace de descarga generado para: " . $path_file);
            }
          } else {
            error_log("❌ DEBUG FileProcessor - Archivo NO existe en ninguna ruta probada");
            foreach ($rutas_a_probar as $ruta_fallida) {
              $full_path = $fileProcessor->getFullPath($ruta_fallida);
              error_log("❌ DEBUG FileProcessor - Ruta fallida: " . $full_path);
            }

            // Archivo no encontrado
            $archivo_procesado['html_preview'] = '<div class="bg-warning text-dark p-2 rounded text-center" style="height: 80px; display: flex; align-items: center; justify-content: center;">
                                                  <div>
                                                    <i class="fa fa-exclamation-triangle mb-1"></i><br>
                                                    <small>No encontrado</small><br>
                                                    <small>' . substr($archivo_procesado['filename'], 0, 8) . '...</small>
                                                  </div>
                                                </div>';

            $html_imagenes .= '<div class="col-3 mb-2">' . $archivo_procesado['html_preview'] . '</div>';
          }

          $archivos_procesados[] = $archivo_procesado;
        }
      }

      // Envolver el HTML de imágenes en un contenedor
      if (!empty($html_imagenes)) {
        $html_imagenes = '<div class="row mt-3">' . $html_imagenes . '</div>';
      }

      $response = [
        'message' => 'Información cargada exitosamente',
        'status' => '1',
        'reprint' => 0,
        'data' => [
          'info' => $info_adicional[0],
          'archivos' => $archivos_procesados,
          'html_imagenes' => $html_imagenes,
          'contador_imagenes' => $contador_imagenes
        ]
      ];
    } catch (Exception $e) {
      $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      $response['message'] = "Error: " . $e->getMessage() . " (Código: $codigoError)";
      error_log("❌ Error en cargar_info_adicional: " . $e->getMessage());
    } finally {
      $database->closeConnection();
    }

    echo json_encode([$response['message'], $response['status'], $response['data'] ?? null]);
    break;

  case 'actualizar_info_adicional':
    $response = [
      'message' => 'Error al actualizar la información',
      'status' => '0',
      'reprint' => 1
    ];

    try {
      $database->openConnection();
      $database->beginTransaction();

      $id_adicional = $_POST['id'] ?? 0;
      $inputs = json_decode($_POST['inputs'], true);

      if (empty($id_adicional)) {
        throw new Exception("ID de información adicional requerido");
      }

      // ✅ PASO 1: Verificar que el registro existe y obtener entidad_id
      $info_existente = $database->selectColumns(
        'cli_adicionales',
        ['id', 'entidad_id', 'entidad_tipo'],
        "id=? AND estado=1",
        [$id_adicional]
      );

      if (empty($info_existente)) {
        throw new Exception("Registro no encontrado");
      }

      $entidad_id = $info_existente[0]['entidad_id'];
      $entidad_tipo = $info_existente[0]['entidad_tipo'];

      // ✅ PASO 2: Obtener la agencia del cliente desde tb_cliente
      $cliente_data = $database->selectColumns(
        'tb_cliente',
        ['idcod_cliente', 'agencia'],
        "idcod_cliente=? AND estado=1",
        [$entidad_id]
      );

      if (empty($cliente_data)) {
        throw new Exception("Cliente no encontrado o inactivo");
      }

      $id_agencia_cliente = $cliente_data[0]['agencia'];

      error_log("✅ Cliente: {$entidad_id} | Agencia: {$id_agencia_cliente}");

      // Mapear datos (soporta formato indexado y asociativo)
      $descripcion = '';
      $latitud = null;
      $longitud = null;
      $altitud = null;
      $precision = null;
      $direccion_texto = '';

      if (!empty($inputs) && is_array($inputs)) {
        if (isset($inputs['descripcion'])) {
          // Formato asociativo
          $descripcion = trim($inputs['descripcion'] ?? '');
          $latitud = !empty($inputs['latitud']) && $inputs['latitud'] !== '' ? (float)$inputs['latitud'] : null;
          $longitud = !empty($inputs['longitud']) && $inputs['longitud'] !== '' ? (float)$inputs['longitud'] : null;
          $altitud = !empty($inputs['altitud']) && $inputs['altitud'] !== '' ? (float)$inputs['altitud'] : null;
          $precision = !empty($inputs['precision_gps']) && $inputs['precision_gps'] !== '' ? (float)$inputs['precision_gps'] : null;
          $direccion_texto = trim($inputs['direccion_texto'] ?? '');
        } else {
          // Formato indexado
          $descripcion = trim($inputs[2] ?? '');
          $latitud = !empty($inputs[3]) && $inputs[3] !== '' ? (float)$inputs[3] : null;
          $longitud = !empty($inputs[4]) && $inputs[4] !== '' ? (float)$inputs[4] : null;
          $altitud = !empty($inputs[5]) && $inputs[5] !== '' ? (float)$inputs[5] : null;
          $precision = !empty($inputs[6]) && $inputs[6] !== '' ? (float)$inputs[6] : null;
          $direccion_texto = trim($inputs[7] ?? '');
        }
      }

      if (empty($descripcion)) {
        throw new Exception("La descripción es obligatoria");
      }

      // Validar coordenadas
      if (($latitud !== null || $longitud !== null) && ($latitud === null || $longitud === null)) {
        throw new Exception("Si proporciona coordenadas, debe incluir tanto latitud como longitud");
      }

      if ($latitud !== null && ($latitud < -90 || $latitud > 90)) {
        throw new Exception("Latitud debe estar entre -90 y 90 grados");
      }

      if ($longitud !== null && ($longitud < -180 || $longitud > 180)) {
        throw new Exception("Longitud debe estar entre -180 y 180 grados");
      }

      // Preparar datos para actualización
      $datos_actualizacion = [
        'descripcion' => $descripcion,
        'latitud' => $latitud,
        'longitud' => $longitud,
        'altitud' => $altitud,
        '`precision`' => $precision,
        'direccion_texto' => $direccion_texto,
        'updated_by' => $_SESSION['userID'] ?? $idusuario ?? 1,
        'updated_at' => date('Y-m-d H:i:s')
      ];

      // Actualizar registro
      $actualizado = $database->update(
        'cli_adicionales',
        $datos_actualizacion,
        "id=?",
        [$id_adicional]
      );

      if (!$actualizado) {
        throw new Exception("Error al actualizar la información");
      }

      // ✅ PROCESAR ARCHIVOS NUEVOS CON PARÁMETROS CORRECTOS
      $archivos_guardados = 0;
      $nombres_archivos = ['archivos_adjuntos', 'archivos', 'files', 'archivos_adicionales'];

      foreach ($nombres_archivos as $nombre) {
        if (!empty($_FILES[$nombre])) {
          $archivos_guardados = procesarArchivosAdicionales(
            $id_adicional,
            $_FILES[$nombre],
            $database,
            $entidad_id,           // ✅ ID del cliente
            $id_agencia_cliente    // ✅ ID de la agencia
          );
          break;
        }
      }

      $database->commit();

      $mensaje = "Información actualizada exitosamente";
      if ($archivos_guardados > 0) {
        $mensaje .= " con {$archivos_guardados} archivo(s) adicional(es)";
      }

      $response = [
        'message' => $mensaje,
        'status' => '1',
        'reprint' => 1,
        'timer' => 3000,
        'archivos_guardados' => $archivos_guardados
      ];

      error_log("✅ Actualización completada: {$mensaje}");
    } catch (Exception $e) {
      $database->rollback();
      $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      $response['message'] = "Error: " . $e->getMessage() . " (Código: $codigoError)";
      error_log("❌ Error en actualización: " . $e->getMessage());
    } finally {
      $database->closeConnection();
    }

    echo json_encode([$response['message'], $response['status'], $response]);
  break;

  case 'eliminar_info_adicional':
    $response = [
      'message' => 'Error al eliminar la información',
      'status' => '0',
      'reprint' => 1
    ];

    try {
      $database->openConnection();
      $database->beginTransaction();

      $id_adicional = $_POST['id'] ?? 0;

      if (empty($id_adicional)) {
        throw new Exception("ID de información adicional requerido");
      }

      // Verificar que el registro existe
      $info_existente = $database->selectColumns(
        'cli_adicionales',
        ['id', 'entidad_id'],
        "id=? AND estado=1",
        [$id_adicional]
      );

      if (empty($info_existente)) {
        throw new Exception("Registro no encontrado");
      }

      // Obtener archivos asociados
      $archivos_asociados = $database->selectColumns(
        'cli_adicional_archivos',
        ['id', 'path_file'],
        "id_adicional=?",
        [$id_adicional]
      );

      // Soft delete
      $eliminado = $database->update(
        'cli_adicionales',
        [
          'estado' => 0,
          'deleted_by' => $_SESSION['userID'] ?? $idusuario ?? 1,
          'deleted_at' => date('Y-m-d H:i:s')
        ],
        "id=?",
        [$id_adicional]
      );

      if (!$eliminado) {
        throw new Exception("Error al eliminar la información");
      }

      // ✅ ELIMINAR ARCHIVOS CON RUTA CORRECTA
      $archivos_eliminados = 0;
      foreach ($archivos_asociados as $archivo) {
        try {
          // ✅ Ruta correcta: 3 niveles arriba
          $ruta_completa = __DIR__ . '/../../../' . $archivo['path_file'];

          error_log("🗑️ Eliminando: " . $ruta_completa);

          if (file_exists($ruta_completa)) {
            if (unlink($ruta_completa)) {
              error_log("✅ Archivo físico eliminado");
            } else {
              error_log("⚠️ No se pudo eliminar archivo físico");
            }
          } else {
            error_log("⚠️ Archivo no existe: " . $ruta_completa);
          }

          $database->delete('cli_adicional_archivos', "id=?", [$archivo['id']]);
          $archivos_eliminados++;
        } catch (Exception $e) {
          error_log("❌ Error eliminando archivo: " . $e->getMessage());
          logerrores("Error eliminando archivo: " . $e->getMessage(), __FILE__, __LINE__);
        }
      }

      // Intentar eliminar carpeta si está vacía
      if (!empty($archivos_asociados)) {
        try {
          $primer_archivo = $archivos_asociados[0]['path_file'];
          $carpeta_adicionales = dirname(__DIR__ . '/../../../' . $primer_archivo);

          if (is_dir($carpeta_adicionales) && count(scandir($carpeta_adicionales)) === 2) {
            rmdir($carpeta_adicionales);
            error_log("✅ Carpeta adicionales eliminada");
          }
        } catch (Exception $e) {
          error_log("⚠️ No se pudo eliminar carpeta: " . $e->getMessage());
        }
      }

      $database->commit();

      $mensaje = "Información eliminada exitosamente";
      if ($archivos_eliminados > 0) {
        $mensaje .= " junto con {$archivos_eliminados} archivo(s)";
      }

      $response = [
        'message' => $mensaje,
        'status' => '1',
        'reprint' => 1,
        'timer' => 3000,
        'archivos_eliminados' => $archivos_eliminados
      ];

      error_log("✅ Eliminación completada");
    } catch (Exception $e) {
      $database->rollback();
      $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      $response['message'] = "Error: " . $e->getMessage() . " (Código: $codigoError)";
      error_log("❌ Error en eliminación: " . $e->getMessage());
    } finally {
      $database->closeConnection();
    }

    echo json_encode([$response['message'], $response['status'], $response]);
    break;

  case 'eliminar_archivo_adicional':
    $response = [
      'message' => 'Error al eliminar el archivo',
      'status' => '0',
      'reprint' => 0
    ];

    try {
      $database->openConnection();
      $database->beginTransaction();

      $id_archivo = $_POST['id'] ?? 0;

      error_log("🗑️ Eliminando archivo ID: {$id_archivo}");

      if (empty($id_archivo)) {
        throw new Exception("ID de archivo requerido");
      }

      // Obtener información del archivo
      $archivo = $database->selectColumns(
        'cli_adicional_archivos',
        ['id', 'path_file'],
        "id=?",
        [$id_archivo]
      );

      if (empty($archivo)) {
        throw new Exception("Archivo no encontrado");
      }

      // ✅ Ruta correcta: 3 niveles arriba
      $ruta_completa = __DIR__ . '/../../../' . $archivo[0]['path_file'];

      error_log("📂 Ruta: " . $ruta_completa);

      // Eliminar archivo físico
      if (file_exists($ruta_completa)) {
        if (unlink($ruta_completa)) {
          error_log("✅ Archivo físico eliminado");
        } else {
          error_log("⚠️ No se pudo eliminar archivo físico");
        }
      } else {
        error_log("⚠️ Archivo no existe");
      }

      // Eliminar registro
      $eliminado = $database->delete('cli_adicional_archivos', "id=?", [$id_archivo]);

      if (!$eliminado) {
        throw new Exception("Error al eliminar el registro del archivo");
      }

      $database->commit();

      $response = [
        'message' => 'Archivo eliminado exitosamente',
        'status' => '1',
        'reprint' => 0
      ];

      error_log("✅ Archivo eliminado exitosamente");
    } catch (Exception $e) {
      $database->rollback();
      $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      $response['message'] = "Error: " . $e->getMessage() . " (Código: $codigoError)";
      error_log("❌ Error: " . $e->getMessage());
    } finally {
      $database->closeConnection();
    }

    echo json_encode([$response['message'], $response['status'], $response]);
    break;

  case 'guardar_info_adicional_cliente':
    $response = [
      'message' => 'Error al guardar la información',
      'status' => '0',
      'reprint' => 1
    ];

    try {
      $database->openConnection();
      $database->beginTransaction();


      $inputs = json_decode($_POST['inputs'], true);
      $archivo = json_decode($_POST['archivo'], true);

      error_log("Inputs: " . print_r($inputs, true));
      error_log("Archivo: " . print_r($archivo, true));

      // Obtener ID del cliente
      $entidad_id = '';
      if (!empty($inputs) && isset($inputs[1])) {
        $entidad_id = $inputs[1];
      }
      if (empty($entidad_id) && !empty($_POST['id'])) {
        $entidad_id = $_POST['id'];
      }
      if (empty($entidad_id) && !empty($archivo) && isset($archivo[0])) {
        $entidad_id = $archivo[0];
      }

      $entidad_tipo = 'cliente';

      // Mapear datos
      if (!empty($inputs) && is_array($inputs)) {
        $descripcion = trim($inputs[2] ?? '');
        $latitud = !empty($inputs[3]) && $inputs[3] !== '' ? (float)$inputs[3] : null;
        $longitud = !empty($inputs[4]) && $inputs[4] !== '' ? (float)$inputs[4] : null;
        $altitud = !empty($inputs[5]) && $inputs[5] !== '' ? (float)$inputs[5] : null;
        $precision = !empty($inputs[6]) && $inputs[6] !== '' ? (float)$inputs[6] : null;
        $direccion_texto = trim($inputs[7] ?? '');

        error_log("👤 Cliente: {$entidad_id} | 📝 Descripción: {$descripcion}");
      }

      // Validaciones
      if (empty($entidad_id)) {
        throw new Exception("ID de cliente requerido");
      }

      if (empty($descripcion)) {
        throw new Exception("La descripción es obligatoria");
      }

      // ✅ Validar cliente y obtener agencia
      $cliente_data = $database->selectColumns(
        'tb_cliente',
        ['idcod_cliente', 'agencia'],
        "estado=1 AND idcod_cliente=?",
        [$entidad_id]
      );

      if (empty($cliente_data)) {
        throw new Exception("Cliente no encontrado");
      }

      $id_agencia_cliente = $cliente_data[0]['agencia'];
      error_log("🏢 Agencia: {$id_agencia_cliente}");

      // Validar coordenadas
      if (($latitud !== null || $longitud !== null) && ($latitud === null || $longitud === null)) {
        throw new Exception("Si proporciona coordenadas, debe incluir tanto latitud como longitud");
      }

      if ($latitud !== null && ($latitud < -90 || $latitud > 90)) {
        throw new Exception("Latitud debe estar entre -90 y 90 grados");
      }

      if ($longitud !== null && ($longitud < -180 || $longitud > 180)) {
        throw new Exception("Longitud debe estar entre -180 y 180 grados");
      }

      // Preparar datos
      $datos_adicionales = [
        'entidad_tipo' => $entidad_tipo,
        'entidad_id' => $entidad_id,
        'descripcion' => $descripcion,
        'latitud' => $latitud,
        'longitud' => $longitud,
        'altitud' => $altitud,
        '`precision`' => $precision,
        'direccion_texto' => $direccion_texto,
        'estado' => 1,
        'created_by' => $_SESSION['userID'] ?? $idusuario ?? 1,
        'created_at' => date('Y-m-d H:i:s')
      ];

      error_log("📊 Datos a insertar: " . print_r($datos_adicionales, true));

      // Insertar
      $id_adicional = $database->insert('cli_adicionales', $datos_adicionales);

      if (!$id_adicional) {
        throw new Exception("Error al insertar información adicional");
      }

      error_log("✅ Registro creado con ID: {$id_adicional}");

      // ✅ PROCESAR ARCHIVOS CON PARÁMETROS CORRECTOS
      $archivos_guardados = 0;
      $nombres_archivos = ['archivos_adjuntos', 'archivos', 'files', 'archivos_adicionales'];

      foreach ($nombres_archivos as $nombre) {
        if (!empty($_FILES[$nombre])) {
          error_log("📎 Procesando archivos desde: {$nombre}");
          $archivos_guardados = procesarArchivosAdicionales(
            $id_adicional,
            $_FILES[$nombre],
            $database,
            $entidad_id,           // ✅ ID del cliente
            $id_agencia_cliente    // ✅ ID de la agencia
          );
          break;
        }
      }

      $database->commit();

      $mensaje = "Información adicional guardada exitosamente";
      if ($archivos_guardados > 0) {
        $mensaje .= " con {$archivos_guardados} archivo(s) adjunto(s)";
      }

      $response = [
        'message' => $mensaje,
        'status' => '1',
        'reprint' => 1,
        'timer' => 3000,
        'id_adicional' => $id_adicional,
        'archivos_guardados' => $archivos_guardados
      ];

      error_log("✅ Guardado completado: {$mensaje}");
    } catch (Exception $e) {
      $database->rollback();
      $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      $response['message'] = "Error: " . $e->getMessage() . " (Código: $codigoError)";
      error_log("❌ Error: " . $e->getMessage());
    } finally {
      $database->closeConnection();
    }

    echo json_encode([$response['message'], $response['status'], $response]);
    break;

  default:
    echo json_encode(['Error: Acción no válida', '0', null]);
    break;
}

// ========================================
// ⭐⭐⭐ FUNCIONES DE APOYO ⭐⭐⭐
// ========================================

function validar_campos_plus($validaciones)
{
  for ($i = 0; $i < count($validaciones); $i++) {
    if ($validaciones[$i][3] == 1) {
      if ($validaciones[$i][0] == $validaciones[$i][1]) {
        return [$validaciones[$i][2], '0', true];
      }
    } elseif ($validaciones[$i][3] == 2) {
      if ($validaciones[$i][0] < $validaciones[$i][1]) {
        return [$validaciones[$i][2], '0', true];
      }
    } elseif ($validaciones[$i][3] == 3) {
      if ($validaciones[$i][0] > $validaciones[$i][1]) {
        return [$validaciones[$i][2], '0', true];
      }
    } elseif ($validaciones[$i][3] == 4) {
      if (validar_expresion_regular($validaciones[$i][0], $validaciones[$i][1])) {
        return [$validaciones[$i][2], '0', true];
      }
    }
  }
  return ["", '0', false];
}

function validar_expresion_regular($cadena, $expresion_regular)
{
  return !preg_match($expresion_regular, $cadena);
}

function concatenar_nombre($array1, $array2, $separador)
{
  $concatenado = '';
  foreach ($array1 as $valor) {
    if (!empty($valor)) {
      $concatenado .= mb_strtoupper($valor, 'UTF-8') . ' ';
    }
  }
  $concatenado2 = '';
  foreach ($array2 as $valor) {
    if (!empty($valor)) {
      $concatenado2 .= mb_strtoupper($valor, 'UTF-8') . ' ';
    }
  }
  return trim($concatenado) . $separador . trim($concatenado2);
}

// ========================================
// ⭐ FUNCIÓN REFACTORIZADA PARA NUEVA ESTRUCTURA
// ========================================
/**
 * Procesa archivos adicionales guardándolos en:
 * imgcoope.microsystemplus.com/{folder}/{cliente}/adicionales/
 * 
 * @param int $id_adicional ID del registro en cli_adicionales
 * @param array $archivos Array de archivos $_FILES
 * @param Database $database Instancia de base de datos
 * @param string $entidad_id ID del cliente
 * @param int $id_agencia ID de la agencia del cliente
 * @return int Cantidad de archivos guardados
 */
function procesarArchivosAdicionales($id_adicional, $archivos, $database, $entidad_id, $id_agencia)
{
  $archivos_guardados = 0;



  try {
    // ✅ Obtener folder de la institución
    $folderInstitucion = (new Agencia($id_agencia))->institucion?->getFolderInstitucion();

    if ($folderInstitucion === null) {
      error_log("❌ No se pudo obtener carpeta de institución");
      throw new Exception("No se pudo obtener la carpeta de la institución");
    }

    error_log("📁 Folder institución: {$folderInstitucion}");

    // ✅ Construir ruta según patrón establecido
    $salida = "../../../"; // 3 niveles arriba desde controllers/actions/
    $entrada = "imgcoope.microsystemplus.com/" . $folderInstitucion . "/" . $entidad_id . "/adicionales";
    $rutaEnServidor = $salida . $entrada;

    error_log("📂 Ruta base: {$entrada}");
    error_log("💾 Ruta servidor: {$rutaEnServidor}");

    // Crear directorio si no existe
    if (!is_dir($rutaEnServidor)) {
      if (!mkdir($rutaEnServidor, 0777, true)) {
        error_log("❌ No se pudo crear directorio");
        throw new Exception("No se pudo crear directorio de archivos adicionales");
      }
      error_log("✅ Directorio creado");
    }

    // Tipos permitidos
    $tipos_permitidos = [
      'image/jpeg',
      'image/jpg',
      'image/png',
      'image/gif',
      'image/bmp',
      'image/webp',
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.ms-excel',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'text/plain'
    ];

    $max_size = 5 * 1024 * 1024; // 5MB

    // Determinar si es múltiple o único
    $es_multiple = is_array($archivos['name']);
    $total_archivos = $es_multiple ? count($archivos['name']) : 1;

    error_log("📊 Total archivos: {$total_archivos}");

    for ($i = 0; $i < $total_archivos; $i++) {
      try {
        // Obtener datos del archivo
        $nombre_original = $es_multiple ? $archivos['name'][$i] : $archivos['name'];
        $tipo = $es_multiple ? $archivos['type'][$i] : $archivos['type'];
        $tamanio = $es_multiple ? $archivos['size'][$i] : $archivos['size'];
        $tmp_name = $es_multiple ? $archivos['tmp_name'][$i] : $archivos['tmp_name'];
        $error = $es_multiple ? $archivos['error'][$i] : $archivos['error'];



        // Validaciones
        if ($error !== UPLOAD_ERR_OK) {
          error_log("❌ Error de carga: {$error}");
          continue;
        }

        if (!is_uploaded_file($tmp_name)) {
          error_log("❌ No es archivo válido");
          continue;
        }

        if (!in_array($tipo, $tipos_permitidos)) {
          error_log("❌ Tipo no permitido: {$tipo}");
          continue;
        }

        if ($tamanio > $max_size) {
          error_log("❌ Archivo muy grande");
          continue;
        }

        // ✅ Generar nombre único
        $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        $hash = substr(md5(uniqid() . time() . $nombre_original), 0, 15);
        $nombre_archivo = "adicional_{$id_adicional}_{$hash}.{$extension}";

        $ruta_completa = $rutaEnServidor . "/" . $nombre_archivo;
        $ruta_relativa = $entrada . "/" . $nombre_archivo;

        error_log("💾 Guardando como: {$nombre_archivo}");
        error_log("🔗 Ruta BD: {$ruta_relativa}");

        // Mover archivo
        if (!move_uploaded_file($tmp_name, $ruta_completa)) {
          error_log("❌ No se pudo mover archivo");
          continue;
        }

        // Verificar
        if (!file_exists($ruta_completa)) {
          error_log("❌ Archivo no existe después de mover");
          continue;
        }

        $file_size = filesize($ruta_completa);
        error_log("✅ Archivo guardado ({$file_size} bytes)");

        // Guardar en BD
        $datos_archivo = [
          'id_adicional' => $id_adicional,
          'path_file' => $ruta_relativa
        ];

        if ($database->insert('cli_adicional_archivos', $datos_archivo)) {
          $archivos_guardados++;
          error_log("✅ Registro en BD exitoso");
        } else {
          error_log("❌ Error al guardar en BD");
          @unlink($ruta_completa);
        }
      } catch (Exception $e) {
        error_log("❌ Error procesando archivo {$i}: " . $e->getMessage());
        logerrores("Error procesando archivo: " . $e->getMessage(), __FILE__, __LINE__);
        continue;
      }
    }
  } catch (Exception $e) {
    error_log("❌ ERROR CRÍTICO: " . $e->getMessage());
    logerrores("Error crítico procesando archivos: " . $e->getMessage(), __FILE__, __LINE__);
  }



  return $archivos_guardados;
}
