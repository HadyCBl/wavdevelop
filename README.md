# Microsystem Plus

Microsystem Plus es una sistema de control contable...

## Descripción

El proyecto está construido con PHP y utiliza Composer para la gestión de dependencias del lado del servidor y NPM (o Yarn) para las dependencias del lado del cliente y el proceso de construcción de assets.

## Características Principales (Inferidas)

*   Utiliza PHP para el backend.
*   Gestión de hojas de cálculo con `phpoffice/phpspreadsheet`.
*   Conversión de números a letras con `luecano/numero-a-letras`.
*   Manejo de variables de entorno con `vlucas/phpdotenv`.
*   Generación de documentos Word con `phpoffice/phpword`.
*   Manejo de JSON Web Tokens (JWT) con `firebase/php-jwt`.
*   Interacción con repositorios Git mediante `czproject/git-php`.
*   Frontend moderno con Tailwind CSS y Alpine.js.
*   Gráficos interactivos con ApexCharts.
*   Componentes de UI como selectores de fecha (Flatpickr) y carruseles (Swiper).
*   Soporte para subida de archivos con Dropzone.
*   Utiliza Webpack para la compilación de assets de frontend.
*   Incluye DaisyUI para componentes de Tailwind CSS.

## Requisitos Previos

*   PHP (versión compatible con las dependencias, probablemente >= 7.4 o 8.0)
*   Composer
*   Node.js y NPM (o Yarn)

## Instalación

1.  **Clonar el repositorio:**
    ```bash
    git clone <url-del-repositorio>
    cd microsystemplus
    ```

2.  **Instalar dependencias de PHP:**
    ```bash
    composer install
    ```

3.  **Instalar dependencias de Node.js:**
    ```bash
    npm install
    # o si usas yarn:
    # yarn install
    ```

4.  **Configurar el entorno:**
    *   Crea un archivo `.env` a partir de `.env.example` (si existe) y configura tus variables de entorno, especialmente las de la base de datos.
    *   Asegúrate de que las variables `$db_host`, `$db_name`, `$db_user`, `$db_password`, `$db_name_general` y `$key1` (usada por `SecureID`) estén disponibles para la aplicación, ya sea a través de variables de entorno cargadas por `phpdotenv` o definidas de otra manera.

## Servidor de Desarrollo

### Backend (PHP)

Para iniciar el servidor de desarrollo de PHP (usando el servidor web incorporado de PHP):

```bash
composer start
```

Esto generalmente iniciará el servidor en `localhost:3000`.

### Frontend (Webpack)

Si necesitas compilar los assets del frontend o ejecutar el servidor de desarrollo de Webpack para hot-reloading:

```bash
npm run start
# o si usas yarn:
# yarn start
```

Esto abrirá automáticamente el proyecto en tu navegador, típicamente en una dirección como `http://localhost:8080` (la configuración de Webpack determinará el puerto exacto).

## Construcción para Producción (Frontend)

Para compilar los assets del frontend para producción:

```bash
npm run build

```

## Estructura de Autocarga (PHP)

El proyecto utiliza la autocarga PSR-4 para las siguientes rutas:

*   `Creditos\Utilidades\` mapeado a `src/funcphp/creditos/`
*   `App\Functions\` mapeado a `src/funcphp/`
*   `App\` mapeado a `includes/Config/`
*   `App\Generic\` mapeado a `src/clases/`

También se carga automáticamente el archivo `includes/Config/helpers.php`.

## Autor

*   **Sotecpro**
    *   Email: admin@sotecprotech.com

---