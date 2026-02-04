# Configuración de Moneda en el Sistema

Este documento explica cómo configurar y utilizar la funcionalidad de moneda en el sistema.

## Configuración de Variables de Entorno

La configuración de la moneda se realiza a través de variables de entorno en el archivo `.env`. A continuación se muestran las variables disponibles:

```
#Env Moneda 
MONEDAID="1"
#definicion De la moneda
DEFAULT_CURRENCY="QUETZALES"  
DEFAULT_CURRENCY_SINGULAR="QUETZAL"  
DEFAULT_CURRENCY_PLURAL="QUETZALES"  
DEFAULT_CURRENCY_CENT_SINGULAR="CENTAVO"  
DEFAULT_CURRENCY_CENT_PLURAL="CENTAVOS"
CONERVSION_MONEDA="7.8"
SYMBOL_CURRENCY="Q"
```

Para cambiar a otra moneda (por ejemplo, pesos), modifica las variables de la siguiente manera:

```
# MONEDAID="40"
# DEFAULT_CURRENCY="PESOS"  
# DEFAULT_CURRENCY_SINGULAR="PESO"  
# DEFAULT_CURRENCY_PLURAL="PESOS"  
# DEFAULT_CURRENCY_CENT_SINGULAR="CENTAVO"  
# DEFAULT_CURRENCY_CENT_PLURAL="CENTAVOS"
# CONERVSION_MONEDA="20.14"
# SYMBOL_CURRENCY="$"
```

## Uso de la Clase CurrencyHelper

Para facilitar el manejo de monedas en el sistema, se ha creado la clase `CurrencyHelper` que proporciona métodos para formatear y convertir valores monetarios.

### Incluir la Clase

```php
include __DIR__ . '/../../../includes/Config/CurrencyHelper.php';
```

### Obtener la Configuración de Moneda

```php
$currencyConfig = CurrencyHelper::getCurrencyConfig();
```

Esto devuelve un array con toda la configuración de moneda:

```php
[
    'principal' => 'QUETZALES',
    'plural' => 'QUETZALES',
    'singular' => 'QUETZAL',
    'cent_singular' => 'CENTAVO',
    'cent_plural' => 'CENTAVOS',
    'symbol' => 'Q',
    'conversion_rate' => 7.8
]
```

### Formatear Valores Monetarios

```php
// Formatear un valor con el símbolo de la moneda
$formattedAmount = CurrencyHelper::formatAmount(1000.50); // Resultado: "Q 1,000.50"

// Formatear un valor sin el símbolo de la moneda
$formattedAmount = CurrencyHelper::formatAmount(1000.50, false); // Resultado: "1,000.50"
```

### Convertir a Dólares

```php
// Convertir un valor de la moneda principal a dólares
$dollars = CurrencyHelper::convertToDollars(1000.50); // Resultado: 128.27 (si la tasa es 7.8)

// Formatear un valor en dólares
$formattedDollars = CurrencyHelper::formatDollars($dollars); // Resultado: "$ 128.27"
```

### Obtener Textos de Moneda

```php
// Obtener el texto de la moneda (singular o plural)
$currencyText = CurrencyHelper::getCurrencyText(1); // Resultado: "QUETZAL"
$currencyText = CurrencyHelper::getCurrencyText(2); // Resultado: "QUETZALES"

// Obtener el texto de los centavos (singular o plural)
$centsText = CurrencyHelper::getCentsText(1.01); // Resultado: "CENTAVO"
$centsText = CurrencyHelper::getCentsText(1.02); // Resultado: "CENTAVOS"
```

## Ejemplo de Uso en un Reporte

```php
// Obtener la configuración de moneda
$currencyConfig = CurrencyHelper::getCurrencyConfig();

// Calcular valores
$amount = 1000.50;
$dollars = CurrencyHelper::convertToDollars($amount);

// Mostrar en el reporte
$pdf->EtiquetaValor('Tipo de moneda:', $currencyConfig['plural']);
$pdf->EtiquetaValor('Monto de la transacción:', CurrencyHelper::formatAmount($amount));
$pdf->EtiquetaValor('Monto en dólares:', CurrencyHelper::formatDollars($dollars));
```

## Ventajas de Usar CurrencyHelper

1. **Centralización**: Toda la lógica relacionada con monedas está en un solo lugar.
2. **Mantenibilidad**: Si es necesario cambiar la forma en que se formatean los valores, solo hay que modificar un archivoc.
3. **Consistencia**: Todos los reportes mostrarán los valores monetarios de la misma manera.
4. **Flexibilidad**: Es fácil cambiar entre diferentes monedas modificando las variables de entorno. 