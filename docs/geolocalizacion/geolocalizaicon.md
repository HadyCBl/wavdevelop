📚 Documentación Completa del Sistema de Geolocalización para Garantías
📑 Tabla de Contenido
Introducción
Arquitectura del Sistema
Base de Datos
Backend - PHP
Frontend - JavaScript
Flujos de Trabajo
Casos de Uso
Guía de Implementación
API y Referencias
Mejoras Futuras
1. Introducción
1.1 Propósito del Sistema
El sistema de geolocalización para garantías permite a los usuarios registrar, visualizar y gestionar la ubicación física de las garantías asociadas a créditos de clientes mediante coordenadas GPS.

1.2 Características Principales
✅ Captura de ubicación mediante GPS del dispositivo
✅ Selección manual de ubicación en mapa interactivo
✅ Visualización individual de garantías en mapa
✅ Vista de múltiples garantías simultáneamente
✅ Geocodificación inversa (coordenadas → dirección)
✅ Integración con Google Maps
✅ Almacenamiento de precisión GPS
┌─────────────────────────────────────────────────┐
│              NAVEGADOR (Cliente)                 │
│  ┌───────────────────────────────────────────┐  │
│  │  Interfaz de Usuario (HTML/Bootstrap)     │  │
│  └───────────────┬───────────────────────────┘  │
│                  │                                │
│  ┌───────────────▼───────────────────────────┐  │
│  │  Lógica JavaScript                        │  │
│  │  - Captura GPS                            │  │
│  │  - Control de Mapa (Leaflet)              │  │
│  │  - Validaciones                           │  │
│  └───────────────┬───────────────────────────┘  │
└──────────────────┼───────────────────────────────┘
                   │ AJAX
                   │
┌──────────────────▼───────────────────────────────┐
│              SERVIDOR (PHP)                      │
│  ┌───────────────────────────────────────────┐  │
│  │  Controlador (cre_indi_02.php)            │  │
│  │  - Procesamiento de solicitudes           │  │
│  │  - Validaciones de negocio                │  │
│  └───────────────┬───────────────────────────┘  │
│                  │                                │
│  ┌───────────────▼───────────────────────────┐  │
│  │  Capa de Acceso a Datos                   │  │
│  │  - Consultas SQL                          │  │
│  │  - Gestión de transacciones               │  │
│  └───────────────┬───────────────────────────┘  │
└──────────────────┼───────────────────────────────┘
                   │
┌──────────────────▼───────────────────────────────┐
│          BASE DE DATOS (MySQL)                   │
│  ┌───────────────────────────────────────────┐  │
│  │  Tablas:                                  │  │
│  │  - cli_garantia                           │  │
│  │  - cli_adicionales (coordenadas)          │  │
│  │  - tb_cliente                             │  │
│  └───────────────────────────────────────────┘  │
└──────────────────────────────────────────────────┘

graph LR
    A[Usuario] -->|Interacción| B[Interfaz Web]
    B -->|Solicitud GPS| C[Navegador API]
    C -->|Coordenadas| B
    B -->|AJAX| D[PHP Backend]
    D -->|SQL Query| E[Base de Datos]
    E -->|Resultados| D
    D -->|JSON Response| B
    B -->|Renderizado| F[Mapa Leaflet]

    1. Usuario abre formulario de nueva garantía
   ↓
2. Usuario completa datos básicos (tipo, descripción, etc.)
   ↓
3. Usuario decide capturar ubicación:
   
   OPCIÓN A: GPS Automático
   3a.1. Clic en botón "GPS"
   3a.2. Navegador solicita permisos
   3a.3. Usuario acepta permisos
   3a.4. Sistema obtiene coordenadas
   3a.5. Actualiza campos de formulario
   3a.6. Actualiza mapa con marcador
   3a.7. Obtiene dirección textual (opcional)
   
   OPCIÓN B: Selección Manual
   3b.1. Clic en botón "Mapa"
   3b.2. Modo selección activado
   3b.3. Usuario hace clic en punto del mapa
   3b.4. Sistema coloca marcador
   3b.5. Actualiza campos de formulario
   3b.6. Obtiene dirección textual (opcional)
   ↓
4. Usuario revisa datos en el mapa
   ↓
5. Usuario hace clic en "Guardar Garantía"
   ↓
6. Sistema valida datos:
   - Campos obligatorios completos
   - Coordenadas válidas (si existen)
   - Saldo capital correcto
   ↓
7. Sistema envía datos al backend:
   - Datos de garantía → cli_garantia
   - Coordenadas → cli_adicionales
   ↓
8. Backend procesa:
   8.1. Inicia transacción
   8.2. Inserta en cli_garantia
   8.3. Obtiene idGarantia
   8.4. Inserta en cli_adicionales (si hay coordenadas)
   8.5. Confirma transacción
   ↓
9. Sistema muestra mensaje de éxito
   ↓
10. Recarga lista de garantías con nueva garantía incluida


1. Usuario ve lista de garantías
   ↓
2. Usuario hace clic en botón "Editar" de una garantía
   ↓
3. Sistema carga datos:
   3.1. Consulta SQL con LEFT JOIN a cli_adicionales
   3.2. Obtiene datos de garantía + coordenadas
   ↓
4. Sistema renderiza formulario:
   4.1. Precarga datos básicos
   4.2. Precarga coordenadas (si existen)
   4.3. Inicializa mapa
   ↓
5. Sistema centra mapa en coordenadas existentes (si hay)
   5.1. Coloca marcador en ubicación guardada
   5.2. Muestra popup informativo
   ↓
6. Usuario modifica datos (opcionales):
   - Puede actualizar ubicación con GPS
   - Puede seleccionar nueva ubicación en mapa
   - Puede limpiar coordenadas
   ↓
7. Usuario hace clic en "Actualizar Garantía"
   ↓
8. Sistema valida datos
   ↓
9. Sistema envía actualización al backend:
   - UPDATE en cli_garantia
   - INSERT o UPDATE en cli_adicionales
   ↓
10. Backend procesa:
    10.1. Inicia transacción
    10.2. Actualiza cli_garantia
    10.3. Verifica si existe registro en cli_adicionales
    10.4. UPDATE si existe, INSERT si no existe
    10.5. Confirma transacción
    ↓
11. Sistema muestra mensaje de éxito
    ↓
12. Recarga lista de garantías con datos actualizados


1. Usuario ve tarjeta de garantía con coordenadas
   ↓
2. Usuario hace clic en botón "Ver en mapa"
   ↓
3. Sistema valida coordenadas:
   - ¿Son válidas? (no nulas, no cero)
   ↓
4. Sistema inicializa mapa (si no está inicializado)
   ↓
5. Sistema centra mapa en coordenadas de la garantía
   5.1. Zoom a nivel 16
   5.2. Remueve marcador anterior
   5.3. Crea marcador rojo en ubicación
   5.4. Muestra popup con información
   ↓
6. Sistema hace scroll hasta el mapa
   ↓
7. Usuario puede:
   - Ver detalles en el popup
   - Hacer clic en "Ver en Google Maps"
   - Interactuar con el mapa

   