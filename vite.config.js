import { defineConfig } from "vite";
import viteCompression from "vite-plugin-compression";
import path from "path";

export default defineConfig(({ mode }) => {
  const isProduction = mode === "production";
  const isDevelopment = !isProduction;

  // Define tus entradas aquí (similar a webpack)
  const entries = {
    // Ejemplo incluido
    another: "./includes/js/vite_another.js",
    caja: "./includes/js/vite_caja.js",
    shared: "./includes/js/vite_shared.js",
    otrosingresos: "./includes/js/vite_otrosingresos.js",
    comprasventas: "./includes/js/vite_comprasventas.js",
    reportes: "./includes/js/vite_reportes.js",
    // Agrega tus nuevas entradas aquí:
    // dashboard: './includes/js/vite_dashboard.js',
    // settings: './includes/js/vite_settings.js',
  };

  return {
    mode: isProduction ? "production" : "development",

    // Raíz del proyecto
    root: process.cwd(),

    // Deshabilitar publicDir para evitar conflictos
    publicDir: false,

    // Configura el publicPath base
    base: isDevelopment ? "/" : "/public/assets/vite-dist/",

    // Múltiples entradas
    build: {
      outDir: "public/assets/vite-dist",
      emptyOutDir: true, // Ahora solo limpia vite-dist, no todo public

      // Generar manifest en la raíz del outDir
      manifest: "manifest.json",

      rollupOptions: {
        input: entries,

        output: {
          // Estructura de archivos similar a webpack
          entryFileNames: isProduction
            ? "js/bundle_[name].[hash:8].js"
            : "js/bundle_[name].js",

          chunkFileNames: isProduction
            ? "js/chunk_[name].[hash:8].js"
            : "js/chunk_[name].js",

          assetFileNames: (assetInfo) => {
            const info = assetInfo.name.split(".");
            const ext = info[info.length - 1];

            if (/png|jpe?g|svg|gif|tiff|bmp|ico/i.test(ext)) {
              return isProduction
                ? `images/[name].[hash:8][extname]`
                : `images/[name][extname]`;
            }

            if (/css/i.test(ext)) {
              return isProduction
                ? `css/bundle_[name].[hash:8][extname]`
                : `css/bundle_[name][extname]`;
            }

            return isProduction
              ? `assets/[name].[hash:8][extname]`
              : `assets/[name][extname]`;
          },

          // Separación de vendors similar a webpack
          manualChunks: isProduction
            ? (id) => {
                if (id.includes("node_modules")) {
                  // jQuery separado
                  if (id.includes("jquery")) {
                    return "jquery";
                  }

                  // Alpine.js separado
                  if (id.includes("alpinejs") || id.includes("@alpinejs")) {
                    return "alpine";
                  }

                  // DataTables separado
                  if (id.includes("datatables.net")) {
                    return "datatables";
                  }

                  // Otros vendors
                  return "vendors";
                }
              }
            : undefined,
        },
      },

      // Source maps solo en desarrollo
      sourcemap: isDevelopment,

      // Minificación
      minify: isProduction ? "terser" : false,
      terserOptions: isProduction
        ? {
            compress: {
              drop_console: true,
              drop_debugger: true,
              pure_funcs: ["console.log", "console.info", "console.debug"],
            },
            mangle: {
              toplevel: true,
              safari10: true,
            },
            format: {
              comments: false,
            },
          }
        : undefined,

      // Performance
      chunkSizeWarningLimit: 512,

      // CSS code splitting
      cssCodeSplit: true,
    },

    // Configuración de desarrollo
    server: {
      port: 5173,
      strictPort: false,
      host: "0.0.0.0",
      open: false,
      cors: true,
      origin: "http://localhost:5173",
      hmr: {
        protocol: "ws",
        host: "localhost",
        port: 5173,
      },
    },

    // Resolución de módulos
    resolve: {
      alias: {
        "@": path.resolve(__dirname, "./includes/js"),
        "@css": path.resolve(__dirname, "./includes/css"),
        "@assets": path.resolve(__dirname, "./assets"),
      },
    },

    // Optimización de dependencias
    optimizeDeps: {
      include: ["jquery", "alpinejs", "datatables.net"],
    },

    // CSS
    css: {
      devSourcemap: isDevelopment,
      // Vite usará automáticamente postcss.config.js
    },

    // Plugins
    plugins: [
      // Compresión en producción
      ...(isProduction
        ? [
            viteCompression({
              algorithm: "gzip",
              ext: ".gz",
              threshold: 8192,
              deleteOriginFile: false,
            }),
          ]
        : []),
    ],

    // Build target
    esbuild: {
      target: "es2015",
      drop: isProduction ? ["console", "debugger"] : [],
    },
  };
});
