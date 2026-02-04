<?php

namespace App\Generic;

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use CzProject\GitPhp\Git;
use Exception;
use PDO;

date_default_timezone_set('America/Guatemala');

/**
 * Clase para manejar los mensajes del sistema
 * 
 * Esta clase se encarga de gestionar los mensajes que se muestran a los usuarios
 * en diferentes secciones del sistema, controlando qué mensajes ha visto cada usuario.
 */
class MensajesSistema
{
    /**
     * @var DatabaseAdapter Instancia de la conexión a la base de datos
     */
    private $db;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = new DatabaseAdapter();
    }

    /**
     * Obtiene los mensajes que debe ver un usuario para un código específico
     * 
     * @param string $codigo Código de la sección o funcionalidad (ej: 'create_garantias')
     * @param int $idUsuario ID del usuario actual
     * @return array Mensajes pendientes de visualizar
     */
    public function obtenerMensajesPendientes($codigo, $idUsuario)
    {
        try {
            $this->db->openConnection(); // Conexión a la base general

            // Consulta para obtener mensajes que:
            // 1. Coincidan con el código especificado
            // 2. No hayan sido vistos por el usuario actual
            // 3. No hayan expirado (fecha_fin es null o mayor a la fecha actual)
            $query = "SELECT m.id, m.titulo, m.mensaje, m.format, m.commit 
                     FROM tb_mensajes_sistema m
                     LEFT JOIN tb_mensajes_usuario mu ON m.id = mu.id_mensaje AND mu.id_usuario = :idUsuario
                     WHERE m.codigo = :codigo 
                     AND mu.view_at IS NULL
                     AND (m.fecha_fin IS NULL OR m.fecha_fin > NOW())
                     ORDER BY m.created_at desc";

            $mensajes = $this->db->getAllResults($query, [
                ':codigo' => $codigo,
                ':idUsuario' => $idUsuario
            ]);

            // Si no hay mensajes, no hace falta procesar más
            if (empty($mensajes)) {
                $this->db->closeConnection();
                return [];
            }

            // Verificar si los commits existen en el repositorio
            $mensajesFiltrados = $this->filtrarMensajesPorCommit($mensajes);

            $this->db->closeConnection();
            return $mensajesFiltrados;
        } catch (Exception $e) {
            // Registrar error
            if (class_exists('Micro\Helpers\Log')) {
                Log::error("Error al obtener mensajes pendientes: " . $e->getMessage(), [
                    'codigo' => $codigo,
                    'idUsuario' => $idUsuario
                ]);
            }
            return [];
        }
    }

    /**
     * Filtra los mensajes según la existencia de sus commits en el repositorio
     * 
     * @param array $mensajes Mensajes a filtrar
     * @return array Mensajes cuyos commits existen en el repositorio
     */
    private function filtrarMensajesPorCommit($mensajes)
    {
        $mensajesFiltrados = [];
        try {
            // Cargar variables de entorno
            if (file_exists(__DIR__ . '/../../.env')) {
                $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
                $dotenv->load();
            }

            // Configurar GitPhp
            $gitBinary = $_ENV['GIT_BINARY'] ?? '/usr/local/cpanel/3rdparty/lib/path-bin/git';
            define('PROJECT_ROOT', __DIR__ . '/../../');

            // Verificar si el binario existe
            if (!file_exists($gitBinary)) {
                // Si no se encuentra el binario, registrar advertencia y devolver todos los mensajes
                if (class_exists('Micro\Helpers\Log')) {
                    Log::warning("Git binary not found at: " . $gitBinary . ". Showing all messages.");
                }
                return $mensajesFiltrados;
            }

            // Crear instancia de Git con el binario específico
            $git = new \CzProject\GitPhp\Git(new \CzProject\GitPhp\Runners\CliRunner($gitBinary));
            $repo = $git->open(PROJECT_ROOT);

            // Obtener todos los hashes de commit del repositorio (limitado a los últimos 500 para optimizar)
            // $commitHashes = $repo->execute('log', '--pretty=format:%H', '-n', '500');
            // $hashesExistentes = array_flip($commitHashes); // Convertir a diccionario para búsqueda rápida

            // Log::info("Found " . count($hashesExistentes) . " commit hashes in the repository.", [
            //     'hashes' => array_keys($hashesExistentes)
            // ]);

            // Filtrar mensajes que tienen commits válidos o sin commit

            foreach ($mensajes as $mensaje) {
                $commitHash = trim($mensaje['commit'] ?? '');

                if (empty($commitHash)) {
                    // Si no hay commit, lo añades o lo ignoras según tu lógica
                    $mensajesFiltrados[] = $mensaje;
                    continue;
                }

                try {

                    // 2. Verifica si el commit está en la rama actual
                    $branchName = $repo->getCurrentBranchName();

                    // Ejecuta el comando Git (depende de cómo lo implementes en tu entorno)
                    $output = $repo->execute('branch', '--contains', $commitHash, '--list', $branchName);

                    Log::info("Processing commit: " . $mensaje['titulo'], [
                        'commit' => $commitHash,
                        'branch' => $branchName,
                        'output' => $output
                    ]);

                    if ( !empty($output)) {
                        // El commit está en la rama actual
                        $mensajesFiltrados[] = $mensaje;
                    } else {
                        Log::warning("Commit no está en la rama actual: " . $mensaje['titulo'], [
                            'commit' => $commitHash,
                            'branch' => $branchName,
                            'output' => $output
                        ]);
                    }
                } catch (Exception $e) {
                    Log::error("Commit inválido: " . $mensaje['titulo'], [
                        'commit' => $commitHash,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (Exception $e) {
            // En caso de error, registrarlo y devolver todos los mensajes
            if (class_exists('Micro\Helpers\Log')) {
                Log::error("Error al filtrar mensajes por commit: " . $e->getMessage());
            }
        }

        return $mensajesFiltrados;
    }
    // private function filtrarMensajesPorCommit($mensajes)
    // {
    //     $mensajesFiltrados = [];
    //     try {
    //         // Cargar variables de entorno
    //         if (file_exists(__DIR__ . '/../../.env')) {
    //             $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    //             $dotenv->load();
    //         }

    //         // Configurar GitPhp
    //         $gitBinary = $_ENV['GIT_BINARY'] ?? '/usr/local/cpanel/3rdparty/lib/path-bin/git';
    //         define('PROJECT_ROOT', __DIR__ . '/../../');

    //         // Verificar si el binario existe
    //         if (!file_exists($gitBinary)) {
    //             // Si no se encuentra el binario, registrar advertencia y devolver todos los mensajes
    //             if (class_exists('Micro\Helpers\Log')) {
    //                 Log::warning("Git binary not found at: " . $gitBinary . ". Showing all messages.");
    //             }
    //             return $mensajesFiltrados;
    //         }

    //         // Crear instancia de Git con el binario específico
    //         $git = new \CzProject\GitPhp\Git(new \CzProject\GitPhp\Runners\CliRunner($gitBinary));
    //         $repo = $git->open(PROJECT_ROOT);

    //         // Obtener todos los hashes de commit del repositorio (limitado a los últimos 500 para optimizar)
    //         // $commitHashes = $repo->execute('log', '--pretty=format:%H', '-n', '500');
    //         // $hashesExistentes = array_flip($commitHashes); // Convertir a diccionario para búsqueda rápida

    //         // Log::info("Found " . count($hashesExistentes) . " commit hashes in the repository.", [
    //         //     'hashes' => array_keys($hashesExistentes)
    //         // ]);

    //         // Filtrar mensajes que tienen commits válidos o sin commit

    //         foreach ($mensajes as $mensaje) {
    //             // Si no tiene commit especificado o el commit existe, incluir el mensaje
    //             /**
    //              * VERSION 1
    //              */
    //             // if (empty($mensaje['commit']) || isset($hashesExistentes[trim($mensaje['commit'])])) {
    //             //     Log::info("Mensaje incluido: " . $mensaje['titulo'], [
    //             //         'commit' => $mensaje['commit'] ?? 'N/A'
    //             //     ]);
    //             //     $mensajesFiltrados[] = $mensaje;
    //             // }

    //             /**
    //              * VERSION 2
    //              */
    //             // $commit = $repo->getCommit(trim($mensaje['commit'] ?? ''));
    //             // Log::info("Processing message: " . $mensaje['titulo'], [
    //             //     'commit' => $mensaje['commit'] ?? 'N/A',
    //             //     'commitExists' => $commit ? 'yes' : 'no',
    //             //     'commitHash' => $commit
    //             // ]);


    //             // if ($commit) {
    //             //     $mensajesFiltrados[] = $mensaje;
    //             // }

    //             /** 
    //              * VERSION 3
    //              * Usar try-catch para manejar errores específicos de commit
    //              */
    //             try {
    //                 $commit = $repo->getCommit(trim($mensaje['commit'] ?? ''));

    //                 Log::info("Processing message: " . $mensaje['titulo'], [
    //                     'commit' => $mensaje['commit'] ?? 'N/A',
    //                     'commitExists' => $commit ? 'yes' : 'no',
    //                     'commitAuthor' => $commit->getAuthorName()
    //                 ]);

    //                 if ($commit) {
    //                     $mensajesFiltrados[] = $mensaje;
    //                 }
    //             } catch (Exception $e) {
    //                 // No agregar el mensaje a los filtrados si el commit no es válido
    //                 // Si quieres incluir mensajes sin commit, descomentar la siguiente línea
    //                 // $mensajesFiltrados[] = $mensaje;

    //                 Log::error("Mensaje excluido por commit inválido: " . $mensaje['titulo'], [
    //                     'commit' => $mensaje['commit'] ?? 'N/A',
    //                     'error' => $e->getMessage()
    //                 ]);
    //             }
    //         }
    //     } catch (Exception $e) {
    //         // En caso de error, registrarlo y devolver todos los mensajes
    //         if (class_exists('Micro\Helpers\Log')) {
    //             Log::error("Error al filtrar mensajes por commit: " . $e->getMessage());
    //         }
    //     }

    //     return $mensajesFiltrados;
    // }

    /**
     * Marca un mensaje como visto por un usuario
     * 
     * @param int $idMensaje ID del mensaje
     * @param int $idUsuario ID del usuario
     * @return bool Éxito de la operación
     */
    public function marcarComoVisto($idMensaje, $idUsuario)
    {
        try {
            $this->db->openConnection(); // Conexión a la base general

            // Verificar si ya existe un registro para este usuario y mensaje
            $registroExistente = $this->db->getSingleResult(
                "SELECT id FROM tb_mensajes_usuario WHERE id_usuario = ? AND id_mensaje = ?",
                [$idUsuario, $idMensaje]
            );

            if ($registroExistente) {
                // Actualizar registro existente
                $resultado = $this->db->update(
                    'tb_mensajes_usuario',
                    ['view_at' => date('Y-m-d H:i:s')],
                    'id = ?',
                    [$registroExistente['id']]
                );
            } else {
                // Crear nuevo registro
                $resultado = $this->db->insert('tb_mensajes_usuario', [
                    'id_usuario' => $idUsuario,
                    'id_mensaje' => $idMensaje,
                    'view_at' => date('Y-m-d H:i:s')
                ]);
            }

            $this->db->closeConnection();
            return (bool)$resultado;
        } catch (Exception $e) {
            // Registrar error
            if (class_exists('Micro\Helpers\Log')) {
                Log::error("Error al marcar mensaje como visto: " . $e->getMessage(), [
                    'idMensaje' => $idMensaje,
                    'idUsuario' => $idUsuario
                ]);
            }
            return false;
        }
    }

    /**
     * Marca todos los mensajes de un código como vistos por un usuario
     * 
     * @param string $codigo Código de la sección o funcionalidad
     * @param int $idUsuario ID del usuario
     * @return bool Éxito de la operación
     */
    public function marcarTodosComoVistos($codigo, $idUsuario)
    {
        try {
            $this->db->openConnection(); // Conexión a la base general
            $this->db->beginTransaction();

            // Obtener todos los mensajes pendientes para este código
            $mensajesPendientes = $this->obtenerMensajesPendientes($codigo, $idUsuario);

            if (empty($mensajesPendientes)) {
                $this->db->commit();
                $this->db->closeConnection();
                return true;
            }

            // Marcar cada mensaje como visto
            foreach ($mensajesPendientes as $mensaje) {
                $this->marcarComoVisto($mensaje['id'], $idUsuario);
            }

            $this->db->commit();
            $this->db->closeConnection();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            // Registrar error
            if (class_exists('Micro\Helpers\Log')) {
                Log::error("Error al marcar todos los mensajes como vistos: " . $e->getMessage(), [
                    'codigo' => $codigo,
                    'idUsuario' => $idUsuario
                ]);
            }
            return false;
        }
    }

    /**
     * Obtiene todos los mensajes de un código específico (vistos y no vistos)
     * 
     * @param string $codigo Código de la sección o funcionalidad
     * @return array Todos los mensajes activos
     */
    public function obtenerTodosMensajes($codigo)
    {
        try {
            $this->db->openConnection(); // Conexión a la base general

            $query = "SELECT id, titulo, mensaje, format 
                     FROM tb_mensajes_sistema 
                     WHERE codigo = :codigo 
                     AND (fecha_fin IS NULL OR fecha_fin > NOW())
                     ORDER BY created_at DESC";

            $mensajes = $this->db->getAllResults($query, [':codigo' => $codigo]);

            $this->db->closeConnection();
            return $mensajes;
        } catch (Exception $e) {
            // Registrar error
            if (class_exists('Micro\Helpers\Log')) {
                Log::error("Error al obtener todos los mensajes: " . $e->getMessage(), [
                    'codigo' => $codigo
                ]);
            }
            return [];
        }
    }

    /**
     * Crea un nuevo mensaje en el sistema
     * 
     * @param string $codigo Código de la sección o funcionalidad
     * @param string $titulo Título del mensaje
     * @param string $mensaje Contenido del mensaje
     * @param string $formato Formato del mensaje (text, html)
     * @param string|null $fechaFin Fecha de expiración (formato Y-m-d H:i:s)
     * @return int|bool ID del mensaje creado o false en caso de error
     */
    public function crearMensaje($codigo, $titulo, $mensaje, $formato = 'text', $fechaFin = null)
    {
        try {
            $this->db->openConnection(); // Conexión a la base general

            $datos = [
                'codigo' => $codigo,
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'format' => $formato,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if ($fechaFin) {
                $datos['fecha_fin'] = $fechaFin;
            }

            $resultado = $this->db->insert('tb_mensajes_sistema', $datos);

            $this->db->closeConnection();
            return $resultado;
        } catch (Exception $e) {
            // Registrar error
            if (class_exists('Micro\Helpers\Log')) {
                Log::error("Error al crear mensaje: " . $e->getMessage(), [
                    'codigo' => $codigo,
                    'titulo' => $titulo
                ]);
            }
            return false;
        }
    }

    /**
     * Renderiza los mensajes para mostrarlos en la interfaz
     * 
     * @param array $mensajes Lista de mensajes a renderizar
     * @param int $resumenLength Longitud del resumen en caracteres (0 para mostrar todo)
     * @return string HTML de los mensajes
     */
    public function renderizarMensajes($mensajes, $resumenLength = 150)
    {
        if (empty($mensajes)) {
            return '';
        }

        $html = '';
        foreach ($mensajes as $mensaje) {
            // Preparar el contenido según el formato
            $contenidoCompleto = $mensaje['format'] === 'html' ? $mensaje['mensaje'] : nl2br(htmlspecialchars($mensaje['mensaje']));

            // Preparar resumen si es necesario
            $mostrarResumen = $resumenLength > 0;
            $resumen = $mostrarResumen ? $this->generarResumen($mensaje['mensaje'], $resumenLength, $mensaje['format'] === 'html') : '';

            $html .= '<div class="alert alert-info alert-dismissible fade show mensaje-sistema" role="alert" data-mensaje-id="' . $mensaje['id'] . '">';
            $html .= '<h5><i class="fa fa-info-circle me-2"></i>' . htmlspecialchars($mensaje['titulo']) . '</h5>';

            if ($mostrarResumen) {
                // Si hay que mostrar un resumen
                $html .= '<div class="mensaje-resumen">' . trim($resumen) . '</div>';
                $html .= '<div class="mt-2">';
                // Quitar saltos de línea y retornos de carro del contenido completo
                $contenidoSinSaltos = str_replace(["\r", "\n"], '', $contenidoCompleto);
                $html .= '<button type="button" class="btn btn-sm btn-outline-primary" onclick="mostrarGuiaActualizaciones(' . $mensaje['id'] . ', \'' . htmlspecialchars(addslashes($contenidoSinSaltos)) . '\', \'' . htmlspecialchars(addslashes($mensaje['titulo'])) . '\')">Ver más</button>';
                $html .= '</div>';
            } else {
                // Mostrar contenido completo
                $html .= '<div class="mensaje-contenido">' . $contenidoCompleto . '</div>';
            }

            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            // $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="marcarMensajeComoVisto(' . $mensaje['id'] . ')"></button>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Genera un resumen del contenido del mensaje
     * 
     * @param string $contenido Contenido completo del mensaje
     * @param int $longitud Longitud máxima del resumen
     * @param bool $esHtml Indica si el contenido es HTML
     * @return string Resumen del contenido
     */
    private function generarResumen($contenido, $longitud, $esHtml = false)
    {
        // Si es HTML, eliminar las etiquetas para el resumen
        if ($esHtml) {
            $contenidoPlano = strip_tags($contenido);
        } else {
            $contenidoPlano = $contenido;
        }

        // Acortar el contenido si excede la longitud máxima
        if (mb_strlen($contenidoPlano) > $longitud) {
            $resumen = mb_substr($contenidoPlano, 0, $longitud);
            // Evitar cortar palabras
            $resumen = mb_substr($resumen, 0, mb_strrpos($resumen, ' '));
            $resumen .= '...';
        } else {
            $resumen = $contenidoPlano;
        }

        return (htmlspecialchars($resumen));
    }
}
