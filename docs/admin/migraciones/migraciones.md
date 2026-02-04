# Documentación de Módulos de Migración

Este documento describe los módulos de migración disponibles en el sistema. Cada módulo corresponde a un caso específico de migración de datos y tiene requisitos específicos para los archivos de entrada.

---

## Tabla de Contenidos
- [Clientes](#clientes)
- [Cremcre Meta](#cremcre-meta)
- [Cremcre Meta Aux](#cremcre-meta-aux)
- [Generación de Planes de Pago](#generación-de-planes-de-pago)
- [Credkar](#credkar)
- [Productos de Ahorro](#productos-de-ahorro)
- [Cuentas de Ahorro](#cuentas-de-ahorro)
- [Movimientos de Ahorro](#movimientos-de-ahorro)

---

## Clientes

### Descripción
Este módulo permite la migración de datos de clientes. El archivo de entrada debe contener todas las columnas obligatorias del formato de migración de clientes.

### Formatos de archivo aceptados
- `.xls`
- `.xlsx`

### Instrucciones
1. Seleccione un archivo en formato Excel que cumpla con los requisitos.
2. Opcionalmente, seleccione si desea validar los DPI duplicados:
   - **Validar DPI duplicados**: El sistema verificará si hay DPI duplicados en los datos.
   - **No validar DPI**: El sistema no realizará esta validación.
3. Haga clic en:
   - **Revisar**: Para verificar los datos antes de migrarlos.
   - **Migrar**: Para iniciar la migración.

---

## Cremcre Meta

### Descripción
Este módulo permite la migración de datos de créditos. El archivo debe contener todas las columnas obligatorias del formato de migración de créditos.

### Formatos de archivo aceptados
- `.xls`
- `.xlsx`

### Instrucciones
1. Seleccione un archivo en formato Excel que cumpla con los requisitos.
2. Haga clic en:
   - **Revisar**: Para verificar los datos antes de migrarlos.
   - **Migrar**: Para iniciar la migración.

---

## Cremcre Meta Aux

### Descripción
Este módulo permite completar la migración de créditos generando los desembolsos. Es necesario haber realizado previamente la migración de créditos con el módulo **Cremcre Meta**.

### Instrucciones
1. Ingrese el código de migración proporcionado por el sistema o el código que asignó a su migración.
2. Haga clic en:
   - **Revisar**: Para verificar los datos antes de migrarlos.
   - **Migrar**: Para iniciar la migración.

---

## Generación de Planes de Pago

### Descripción
Este módulo permite generar los planes de pago para los créditos migrados. Es necesario haber realizado previamente la migración de créditos con el módulo **Cremcre Meta**.

### Instrucciones
1. Ingrese el código de migración proporcionado por el sistema o el código que asignó a su migración.
2. Haga clic en:
   - **Revisar**: Para verificar los datos antes de generar los planes de pago.
   - **Migrar**: Para iniciar la generación de los planes de pago.

---

## Credkar

### Descripción
Este módulo permite la migración de datos de pagos de créditos (kardex). El archivo debe contener todas las columnas obligatorias del formato de migración de pagos de créditos.

### Formatos de archivo aceptados
- `.xls`
- `.xlsx`

### Instrucciones
1. Seleccione un archivo en formato Excel que cumpla con los requisitos.
2. Haga clic en:
   - **Revisar**: Para verificar los datos antes de migrarlos.
   - **Migrar**: Para iniciar la migración.

---

## Productos de Ahorro

### Descripción
Este módulo permite la migración de datos de productos de ahorro. El archivo debe contener todas las columnas obligatorias del formato de migración de productos de ahorro.

### Formatos de archivo aceptados
- `.xls`
- `.xlsx`

### Instrucciones
1. Seleccione un archivo en formato Excel que cumpla con los requisitos.
2. Haga clic en:
   - **Revisar**: Para verificar los datos antes de migrarlos.
   - **Migrar**: Para iniciar la migración.

---

## Cuentas de Ahorro

### Descripción
Este módulo permite la migración de datos de cuentas de ahorro. El archivo debe contener todas las columnas obligatorias del formato de migración de cuentas de ahorro.

### Formatos de archivo aceptados
- `.xls`
- `.xlsx`

### Instrucciones
1. Seleccione un archivo en formato Excel que cumpla con los requisitos.
2. Configure los siguientes campos:
   - **Campo en la data para seleccionar al cliente**: Especifique el campo que identifica al cliente en los datos.
   - **Campo en la tabla de clientes**: Especifique el campo que identifica al cliente en la base de datos.
   - **Campo en la data para seleccionar el producto**: Especifique el campo que identifica el producto en los datos.
   - **Campo en la tabla de productos de ahorro**: Especifique el campo que identifica el producto en la base de datos.
3. Opcionalmente, configure las siguientes opciones:
   - **Ignorar registros si el cliente no existe**.
   - **Ignorar registros si el producto no existe**.
4. Haga clic en:
   - **Revisar**: Para verificar los datos antes de migrarlos.
   - **Migrar**: Para iniciar la migración.

---

## Movimientos de Ahorro

### Descripción
Este módulo permite la migración de datos de movimientos de ahorro. El archivo debe contener todas las columnas obligatorias del formato de migración de movimientos de ahorro.

### Formatos de archivo aceptados
- `.xls`
- `.xlsx`
- `.json`

### Instrucciones
1. Seleccione un archivo en formato Excel o JSON que cumpla con los requisitos.
2. Configure los siguientes campos:
   - **Campo en la data para seleccionar la cuenta**: Especifique el campo que identifica la cuenta en los datos.
   - **Campo en la tabla de cuentas de ahorro**: Especifique el campo que identifica la cuenta en la base de datos.
3. Opcionalmente, configure las siguientes opciones:
   - **Ignorar registros si la cuenta no existe**.
4. Configure el seguimiento de registros:
   - Indique cada cuántos registros procesados se mostrará el progreso.
5. Haga clic en:
   - **Revisar**: Para verificar los datos antes de migrarlos.
   - **Migrar**: Para iniciar la migración.

---

### Notas Generales
- Asegúrese de que los archivos cargados cumplan con los formatos y requisitos especificados para cada módulo.
- Los datos deben estar correctamente formateados antes de realizar la carga.
- Si encuentra errores durante la migración, revise los mensajes de progreso y corrija los datos según sea necesario.

---

**Última actualización:** _[05-04-2025]_