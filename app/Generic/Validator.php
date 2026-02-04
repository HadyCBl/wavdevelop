<?php
namespace Micro\Generic;

use App\DatabaseAdapter;
use Exception;

/**
 * Clase Validator simple inspirada en Illuminate/Validator.
 * Soporta reglas en formato "required|email|min:3|max:255" o array ['required', 'email'].
 * Opcional: pasar instancia de DatabaseAdapter para reglas "unique" y "exists".
 *
 * Reglas disponibles:
 * - required: Campo obligatorio
 * - optional/nullable: Campo opcional, si está vacío no valida las demás reglas
 * - validate_if:field,value: Solo valida las demás reglas cuando field=value (ignora TODO si no cumple)
 * - email: Debe ser un email válido
 * - numeric: Debe ser numérico
 * - integer: Debe ser un entero
 * - string: Debe ser una cadena de texto
 * - min:n / max:n: Para strings valida longitud, para numeric valida valor (auto-detectado)
 * - min_length:n / max_length:n: Valida longitud de string específicamente
 * - min_value:n / max_value:n: Valida valor numérico específicamente
 * - between:min,max: Entre dos valores (longitud para strings, valor para números)
 * - date: Debe ser una fecha válida
 * - in:val1,val2,val3: Debe estar en la lista de valores
 * - regex:pattern: Debe coincidir con el patrón regex
 * - confirmed: Debe coincidir con el campo {campo}_confirmation
 * - size:n: Tamaño específico (longitud para strings, valor para números)
 * - unique:table,column,exceptValue,exceptColumn: Valor único en BD
 * - exists:table,column: Valor debe existir en BD
 *
 * Ejemplo con campo opcional:
 * $rules = [
 *     'email' => 'optional|email',  // Email opcional pero si se ingresa debe ser válido
 *     'edad' => 'optional|numeric|min_value:18|max_value:100'
 * ];
 *
 * Ejemplo con validación de longitud vs valor:
 * $rules = [
 *     'nombre' => 'required|string|min_length:3|max_length:50',  // Longitud de 3-50 caracteres
 *     'edad' => 'required|numeric|min_value:18|max_value:100',   // Valor entre 18-100
 *     'codigo' => 'required|min:3|max:10',  // Auto-detecta: si es string valida longitud, si es número valida valor
 * ];
 *
 * Uso:
 * $v = new Validator($data, $rules, $messages, $dbAdapter);
 * if ($v->fails()) { $errors = $v->errors(); }
 */
class Validator
{
    protected array $data = [];
    protected array $rules = [];
    protected array $messages = [];
    protected array $customAttributes = [];
    protected array $errors = [];
    protected ?DatabaseAdapter $db = null;

    protected static array $defaultMessages = [
        'required' => 'El campo :attribute es obligatorio.',
        'email' => 'El campo :attribute debe ser un email válido.',
        'numeric' => 'El campo :attribute debe ser numérico.',
        'integer' => 'El campo :attribute debe ser un entero.',
        'string' => 'El campo :attribute debe ser una cadena de texto.',
        'min.string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'max.string' => 'El campo :attribute no debe exceder :max caracteres.',
        'min.numeric' => 'El campo :attribute debe ser como mínimo :min.',
        'max.numeric' => 'El campo :attribute debe ser como máximo :max.',
        'min_length' => 'El campo :attribute debe tener al menos :min caracteres.',
        'max_length' => 'El campo :attribute no debe exceder :max caracteres.',
        'min_value' => 'El campo :attribute debe ser como mínimo :min.',
        'max_value' => 'El campo :attribute debe ser como máximo :max.',
        'between' => 'El campo :attribute debe estar entre :min y :max.',
        'date' => 'El campo :attribute debe ser una fecha válida.',
        'in' => 'El campo :attribute no es una opción válida.',
        'regex' => 'El campo :attribute tiene un formato inválido.',
        'confirmed' => 'El campo :attribute no coincide con la confirmación.',
        'size' => 'El campo :attribute debe tener tamaño :size.',
        'unique' => 'El campo :attribute ya está en uso.',
        'exists' => 'El valor seleccionado en :attribute no existe.',
        // Validaciones de fechas
        'after' => 'El campo :attribute debe ser una fecha posterior a :date.',
        'before' => 'El campo :attribute debe ser una fecha anterior a :date.',
        'after_or_equal' => 'El campo :attribute debe ser una fecha posterior o igual a :date.',
        'before_or_equal' => 'El campo :attribute debe ser una fecha anterior o igual a :date.',
        'date_format' => 'El campo :attribute no coincide con el formato :format.',
        // Validaciones numéricas
        'digits' => 'El campo :attribute debe tener :digits dígitos.',
        'digits_between' => 'El campo :attribute debe tener entre :min y :max dígitos.',
        'decimal' => 'El campo :attribute debe tener :decimal decimales.',
        'multiple_of' => 'El campo :attribute debe ser múltiplo de :value.',
        // Validaciones de strings
        'starts_with' => 'El campo :attribute debe comenzar con uno de los siguientes valores: :values.',
        'ends_with' => 'El campo :attribute debe terminar con uno de los siguientes valores: :values.',
        'contains' => 'El campo :attribute debe contener :value.',
        'lowercase' => 'El campo :attribute debe estar en minúsculas.',
        'uppercase' => 'El campo :attribute debe estar en mayúsculas.',
        // Validaciones relacionales
        'same' => 'El campo :attribute debe coincidir con :other.',
        'different' => 'El campo :attribute debe ser diferente de :other.',
        'gt' => 'El campo :attribute debe ser mayor que :other.',
        'gte' => 'El campo :attribute debe ser mayor o igual que :other.',
        'lt' => 'El campo :attribute debe ser menor que :other.',
        'lte' => 'El campo :attribute debe ser menor o igual que :other.',
        // Validaciones avanzadas
        'required_if' => 'El campo :attribute es obligatorio cuando :other es :value.',
        'required_unless' => 'El campo :attribute es obligatorio a menos que :other esté en :values.',
        'required_with' => 'El campo :attribute es obligatorio cuando :values está presente.',
        'required_without' => 'El campo :attribute es obligatorio cuando :values no está presente.',
    ];

    public function __construct(array $data, array $rules, array $messages = [], ?DatabaseAdapter $db = null, array $customAttributes = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = array_merge(self::$defaultMessages, $messages);
        $this->db = $db; // ahora opcional; si es null se creará cuando se necesite
        $this->customAttributes = $customAttributes; 
    }

    /**
     * Permite inyectar manualmente una instancia de DatabaseAdapter si lo deseas.
     */
    public function setDatabaseAdapter(DatabaseAdapter $db): void
    {
        $this->db = $db;
    }

    /**
     * Obtiene la instancia de DatabaseAdapter; la crea si no existe (lazy instantiation).
     * Lanzará excepción si la creación falla (por ejemplo variables de entorno faltantes).
     */
    protected function getDb(): DatabaseAdapter
    {
        if ($this->db === null) {
            // crea una instancia por defecto
            $this->db = new DatabaseAdapter();
        }
        return $this->db;
    }

    public static function make(array $data, array $rules, array $messages = [], ?DatabaseAdapter $db = null, array $customAttributes = []): self
    {
        return new self($data, $rules, $messages, $db, $customAttributes);
    }

    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $ruleSet) {
            $value = $this->data[$field] ?? null;
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);

            // Verificar si tiene regla validate_if
            $validateIfRule = null;
            foreach ($rules as $rule) {
                [$ruleName, $params] = $this->parseRule($rule);
                if ($ruleName === 'validate_if') {
                    $validateIfRule = $params;
                    break;
                }
            }
            
            // Si tiene validate_if, verificar si se debe validar
            if ($validateIfRule !== null) {
                $conditionField = $validateIfRule[0] ?? null;
                $conditionValue = $validateIfRule[1] ?? null;
                $actualValue = $this->data[$conditionField] ?? null;
                
                // Si la condición NO se cumple, saltar TODO el campo
                if ($actualValue != $conditionValue) {
                    continue;
                }
            }

            // Verificar si el campo es opcional/nullable
            $isOptional = $this->hasRule($rules, ['optional', 'nullable']);
            
            // Verificar si tiene reglas condicionales de required
            $hasConditionalRequired = $this->hasRule($rules, ['required_if', 'required_unless', 'required_with', 'required_without']);
            
            // Si es opcional, está vacío, y NO tiene reglas required condicionales, saltar validaciones
            if ($isOptional && $this->isEmpty($value) && !$hasConditionalRequired) {
                continue;
            }
            
            // Si es opcional, está vacío, pero TIENE reglas condicionales, solo validar las reglas required*
            $onlyValidateRequired = $isOptional && $this->isEmpty($value) && $hasConditionalRequired;

            // Verificar si tiene alguna regla required (incluyendo condicionales)
            $hasAnyRequired = $this->hasRule($rules, ['required', 'required_if', 'required_unless', 'required_with', 'required_without']);
            
            foreach ($rules as $rule) {
                [$ruleName, $params] = $this->parseRule($rule);

                // Saltar las reglas de marcador opcional/nullable/validate_if ya que ya fueron procesadas
                if (in_array($ruleName, ['optional', 'nullable', 'validate_if'])) {
                    continue;
                }
                
                // Si solo debemos validar required*, saltar las demás
                if ($onlyValidateRequired && !str_starts_with($ruleName, 'required')) {
                    continue;
                }
                
                // Si el campo está vacío, NO tiene optional, NO tiene required, y NO es una regla required*, saltarla
                // Esto permite que reglas como 'date', 'email' se salten automáticamente cuando el campo está vacío
                // sin necesidad de marcar el campo como optional explícitamente
                $isRequiredRule = str_starts_with($ruleName, 'required');
                if ($this->isEmpty($value) && !$isOptional && !$hasAnyRequired && !$isRequiredRule) {
                    continue;
                }

                $method = 'validate' . ucfirst($ruleName);
                $passed = true;

                if (method_exists($this, $method)) {
                    $passed = $this->{$method}($field, $value, $params);
                } else {
                    // reglas genéricas manejadas aquí
                    $passed = $this->applyGenericRule($ruleName, $field, $value, $params);
                }

                if (!$passed) {
                    $this->addError($field, $ruleName, $params);
                    // Si falló una regla required*, detener validación de este campo
                    if ($onlyValidateRequired) {
                        break;
                    }
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Verifica si un conjunto de reglas contiene alguna de las reglas especificadas
     */
    protected function hasRule(array $rules, array $ruleNames): bool
    {
        foreach ($rules as $rule) {
            [$ruleName, ] = $this->parseRule($rule);
            if (in_array($ruleName, $ruleNames)) {
                return true;
            }
        }
        return false;
    }

    protected function parseRule(string $rule): array
    {
        if (strpos($rule, ':') !== false) {
            [$name, $paramStr] = explode(':', $rule, 2);
            $params = explode(',', $paramStr);
        } else {
            $name = $rule;
            $params = [];
        }
        return [trim($name), $params];
    }

    protected function applyGenericRule(string $rule, string $field, $value, array $params): bool
    {
        switch ($rule) {
            case 'required':
                return !($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && count($value) === 0));
            
            case 'optional':
            case 'nullable':
            case 'validate_if':
                // Estas reglas son solo marcadores, siempre pasan
                return true;
            
            case 'email':
                if ($this->isEmpty($value)) return false;
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            
            case 'numeric':
                if ($this->isEmpty($value)) return false;
                return is_numeric($value);
            
            case 'integer':
                if ($this->isEmpty($value)) return false;
                return filter_var($value, FILTER_VALIDATE_INT) !== false;
            
            case 'string':
                if ($this->isEmpty($value)) return false;
                return is_string($value);
            
            case 'date':
                if ($this->isEmpty($value)) return false;
                return (bool)strtotime($value);
            
            // Validaciones antiguas min/max (mantienen compatibilidad pero detectan tipo)
            case 'min':
                if ($this->isEmpty($value)) return false;
                $min = $params[0] ?? 0;
                // Determinar si debemos validar como número o como string
                if ($this->shouldValidateAsNumeric($field)) {
                    return is_numeric($value) && $value >= $min;
                }
                return mb_strlen((string)$value) >= (int)$min;
            
            case 'max':
                if ($this->isEmpty($value)) return false;
                $max = $params[0] ?? PHP_INT_MAX;
                // Determinar si debemos validar como número o como string
                if ($this->shouldValidateAsNumeric($field)) {
                    return is_numeric($value) && $value <= $max;
                }
                return mb_strlen((string)$value) <= (int)$max;
            
            // Nuevas validaciones específicas para longitud de string
            case 'min_length':
                if ($this->isEmpty($value)) return false;
                $min = (int)($params[0] ?? 0);
                return mb_strlen((string)$value) >= $min;
            
            case 'max_length':
                if ($this->isEmpty($value)) return false;
                $max = (int)($params[0] ?? PHP_INT_MAX);
                return mb_strlen((string)$value) <= $max;
            
            // Nuevas validaciones específicas para valores numéricos
            case 'min_value':
                if ($this->isEmpty($value)) return false;
                if (!is_numeric($value)) return false;
                $min = $params[0] ?? 0;
                return $value >= $min;
            
            case 'max_value':
                if ($this->isEmpty($value)) return false;
                if (!is_numeric($value)) return false;
                $max = $params[0] ?? PHP_INT_MAX;
                return $value <= $max;
            
            case 'between':
                if ($this->isEmpty($value)) return false;
                $min = $params[0] ?? 0;
                $max = $params[1] ?? PHP_INT_MAX;
                if ($this->shouldValidateAsNumeric($field)) {
                    return is_numeric($value) && $value >= $min && $value <= $max;
                }
                $len = mb_strlen((string)$value);
                return $len >= (int)$min && $len <= (int)$max;
            
            case 'in':
                if ($this->isEmpty($value)) return false;
                return in_array($value, $params, true);
            
            case 'regex':
                if ($this->isEmpty($value)) return false;
                return preg_match($params[0] ?? '//', (string)$value) === 1;
            
            case 'confirmed':
                $confirmField = $field . '_confirmation';
                return ($this->data[$confirmField] ?? null) === $value;
            
            case 'size':
                if ($this->isEmpty($value)) return false;
                $size = $params[0] ?? 0;
                if ($this->shouldValidateAsNumeric($field)) {
                    return is_numeric($value) && $value == $size;
                }
                return mb_strlen((string)$value) == (int)$size;
            
            case 'unique':
                return $this->validateUnique($field, $value, $params);
            
            case 'exists':
                return $this->validateExists($field, $value, $params);
            
            // Validaciones de fechas
            case 'after':
                if ($this->isEmpty($value)) return false;
                $compareDateStr = $params[0] ?? 'now';
                // Si el parámetro es un campo, usar su valor
                if (isset($this->data[$compareDateStr])) {
                    $compareDateStr = $this->data[$compareDateStr];
                }
                $compareDate = strtotime($compareDateStr);
                $valueDate = strtotime($value);
                return $valueDate !== false && $compareDate !== false && $valueDate > $compareDate;
            
            case 'before':
                if ($this->isEmpty($value)) return false;
                $compareDateStr = $params[0] ?? 'now';
                // Si el parámetro es un campo, usar su valor
                if (isset($this->data[$compareDateStr])) {
                    $compareDateStr = $this->data[$compareDateStr];
                }
                $compareDate = strtotime($compareDateStr);
                $valueDate = strtotime($value);
                return $valueDate !== false && $compareDate !== false && $valueDate < $compareDate;
            
            case 'after_or_equal':
                if ($this->isEmpty($value)) return false;
                $compareDateStr = $params[0] ?? 'now';
                // Si el parámetro es un campo, usar su valor
                if (isset($this->data[$compareDateStr])) {
                    $compareDateStr = $this->data[$compareDateStr];
                }
                $compareDate = strtotime($compareDateStr);
                $valueDate = strtotime($value);
                return $valueDate !== false && $compareDate !== false && $valueDate >= $compareDate;
            
            case 'before_or_equal':
                if ($this->isEmpty($value)) return false;
                $compareDateStr = $params[0] ?? 'now';
                // Si el parámetro es un campo, usar su valor
                if (isset($this->data[$compareDateStr])) {
                    $compareDateStr = $this->data[$compareDateStr];
                }
                $compareDate = strtotime($compareDateStr);
                $valueDate = strtotime($value);
                return $valueDate !== false && $compareDate !== false && $valueDate <= $compareDate;
            
            case 'date_format':
                if ($this->isEmpty($value)) return false;
                $format = $params[0] ?? 'Y-m-d';
                $d = \DateTime::createFromFormat($format, $value);
                return $d && $d->format($format) === $value;
            
            // Validaciones numéricas
            case 'digits':
                if ($this->isEmpty($value)) return false;
                $digits = $params[0] ?? 0;
                return preg_match('/^\d{' . $digits . '}$/', (string)$value) === 1;
            
            case 'digits_between':
                if ($this->isEmpty($value)) return false;
                $min = $params[0] ?? 0;
                $max = $params[1] ?? PHP_INT_MAX;
                $length = strlen((string)$value);
                return preg_match('/^\d+$/', (string)$value) === 1 && $length >= $min && $length <= $max;
            
            case 'decimal':
                if ($this->isEmpty($value)) return false;
                $decimals = $params[0] ?? null;
                if ($decimals === null) {
                    return preg_match('/^-?\d+\.\d+$/', (string)$value) === 1;
                }
                $pattern = '/^-?\d+\.\d{' . $decimals . '}$/';
                return preg_match($pattern, (string)$value) === 1;
            
            case 'multiple_of':
                if ($this->isEmpty($value)) return false;
                if (!is_numeric($value)) return false;
                $multiple = $params[0] ?? 1;
                if ($multiple == 0) return false;
                return fmod((float)$value, (float)$multiple) == 0;
            
            // Validaciones de strings
            case 'starts_with':
                if ($this->isEmpty($value)) return false;
                $str = (string)$value;
                foreach ($params as $prefix) {
                    if (str_starts_with($str, $prefix)) {
                        return true;
                    }
                }
                return false;
            
            case 'ends_with':
                if ($this->isEmpty($value)) return false;
                $str = (string)$value;
                foreach ($params as $suffix) {
                    if (str_ends_with($str, $suffix)) {
                        return true;
                    }
                }
                return false;
            
            case 'contains':
                if ($this->isEmpty($value)) return false;
                $str = (string)$value;
                $needle = $params[0] ?? '';
                return str_contains($str, $needle);
            
            case 'lowercase':
                if ($this->isEmpty($value)) return false;
                return (string)$value === mb_strtolower((string)$value, 'UTF-8');
            
            case 'uppercase':
                if ($this->isEmpty($value)) return false;
                return (string)$value === mb_strtoupper((string)$value, 'UTF-8');
            
            // Validaciones relacionales
            case 'same':
                $otherField = $params[0] ?? null;
                if (!$otherField) return false;
                return ($this->data[$otherField] ?? null) === $value;
            
            case 'different':
                $otherField = $params[0] ?? null;
                if (!$otherField) return false;
                return ($this->data[$otherField] ?? null) !== $value;
            
            case 'gt':
                $otherField = $params[0] ?? null;
                if (!$otherField) return false;
                $otherValue = $this->data[$otherField] ?? null;
                if ($this->shouldValidateAsNumeric($field)) {
                    return is_numeric($value) && is_numeric($otherValue) && $value > $otherValue;
                }
                return mb_strlen((string)$value) > mb_strlen((string)$otherValue);
            
            case 'gte':
                $otherField = $params[0] ?? null;
                if (!$otherField) return false;
                $otherValue = $this->data[$otherField] ?? null;
                if ($this->shouldValidateAsNumeric($field)) {
                    return is_numeric($value) && is_numeric($otherValue) && $value >= $otherValue;
                }
                return mb_strlen((string)$value) >= mb_strlen((string)$otherValue);
            
            case 'lt':
                $otherField = $params[0] ?? null;
                if (!$otherField) return false;
                $otherValue = $this->data[$otherField] ?? null;
                if ($this->shouldValidateAsNumeric($field)) {
                    return is_numeric($value) && is_numeric($otherValue) && $value < $otherValue;
                }
                return mb_strlen((string)$value) < mb_strlen((string)$otherValue);
            
            case 'lte':
                $otherField = $params[0] ?? null;
                if (!$otherField) return false;
                $otherValue = $this->data[$otherField] ?? null;
                if ($this->shouldValidateAsNumeric($field)) {
                    return is_numeric($value) && is_numeric($otherValue) && $value <= $otherValue;
                }
                return mb_strlen((string)$value) <= mb_strlen((string)$otherValue);
            
            // Validaciones avanzadas
            case 'required_if':
                $otherField = $params[0] ?? null;
                $otherValue = $params[1] ?? null;
                if (!$otherField) return true;
                $actualOtherValue = $this->data[$otherField] ?? null;
                if ($actualOtherValue == $otherValue) {
                    return !$this->isEmpty($value);
                }
                return true;
            
            case 'required_unless':
                $otherField = $params[0] ?? null;
                if (!$otherField) return true;
                $actualOtherValue = $this->data[$otherField] ?? null;
                $exceptValues = array_slice($params, 1);
                if (!in_array($actualOtherValue, $exceptValues, true)) {
                    return !$this->isEmpty($value);
                }
                return true;
            
            case 'required_with':
                foreach ($params as $otherField) {
                    if (!$this->isEmpty($this->data[$otherField] ?? null)) {
                        return !$this->isEmpty($value);
                    }
                }
                return true;
            
            case 'required_without':
                foreach ($params as $otherField) {
                    if ($this->isEmpty($this->data[$otherField] ?? null)) {
                        return !$this->isEmpty($value);
                    }
                }
                return true;
            
            default:
                // regla desconocida: asumir que pasa para no bloquear
                return true;
        }
    }

    /**
     * Determina si un campo debe ser validado como numérico basándose en sus reglas
     */
    protected function shouldValidateAsNumeric(string $field): bool
    {
        $fieldRules = $this->rules[$field] ?? [];
        $rules = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
        
        foreach ($rules as $rule) {
            [$ruleName, ] = $this->parseRule($rule);
            if (in_array($ruleName, ['numeric', 'integer', 'min_value', 'max_value'])) {
                return true;
            }
            if (in_array($ruleName, ['string', 'email', 'min_length', 'max_length'])) {
                return false;
            }
        }
        
        // Si no hay indicación clara, verificar si el valor es numérico
        $value = $this->data[$field] ?? null;
        return is_numeric($value);
    }

    protected function isEmpty($value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '') || (is_array($value) && count($value) === 0);
    }

protected function addError(string $field, string $rule, array $params = [])
    {
        // MEJORAR: Buscar mensaje específico para campo.regla
        $fieldRuleKey = "$field.$rule";
        
        $msgKey = $rule;
        if ($rule === 'min' || $rule === 'max') {
            $msgKey .= $this->shouldValidateAsNumeric($field) ? '.numeric' : '.string';
        }
        
        // Prioridad: 1. campo.regla, 2. regla, 3. mensaje por defecto
        $template = $this->messages[$fieldRuleKey] 
                    ?? $this->messages[$msgKey] 
                    ?? ($this->messages[$rule] 
                    ?? 'El campo :attribute es inválido.');

        // Usar nombre personalizado del campo si existe, si no, formatear el nombre del campo
        if (isset($this->customAttributes[$field])) {
            $attributeName = $this->customAttributes[$field];
        } else {
            // Formatear automáticamente: reemplazar guiones bajos por espacios
            $attributeName = str_replace('_', ' ', $field);
        }

        // Formatear también el campo "other" si es un nombre de campo
        $otherFieldName = $params[0] ?? '';
        if ($otherFieldName && isset($this->data[$otherFieldName])) {
            // Es un nombre de campo, formatearlo
            $otherFieldName = $this->customAttributes[$otherFieldName] ?? str_replace('_', ' ', $otherFieldName);
        }

        $replacements = [
            ':attribute' => $attributeName,
            ':field' => $field,
            ':min' => $params[0] ?? '',
            ':max' => $params[1] ?? ($params[0] ?? ''),
            ':size' => $params[0] ?? '',
            ':digits' => $params[0] ?? '',
            ':decimal' => $params[0] ?? '',
            ':value' => $params[0] ?? '',
            ':values' => implode(', ', $params),
            ':date' => $otherFieldName ?: ($params[0] ?? ''),
            ':format' => $params[0] ?? '',
            ':other' => $otherFieldName ?: ($params[0] ?? ''),
        ];
        $message = strtr($template, $replacements);

        $this->errors[$field][] = $message;
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function passes(): bool
    {
        return $this->validate();
    }

    public function errors(): array
    {
        // validar si no se validó antes
        if ($this->errors === []) {
            $this->validate();
        }
        return $this->errors;
    }

    public function first(string $field): ?string
    {
        $errs = $this->errors();
        return $errs[$field][0] ?? null;
    }

    public function firstOnErrors(): ?string
    {
        $errs = $this->errors();
        foreach ($errs as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }
        return null;
    }

    /**
     * Regla unique: unique:table,column,exceptValue,exceptColumn
     * ejemplo: unique:users,email,5,id  --> ignora registro id=5
     */
    protected function validateUnique(string $field, $value, array $params): bool
    {
        if ($this->isEmpty($value)) return true;

        try {
            $db = $this->getDb();
            $db->openConnection();

            $table = $params[0] ?? null;
            $column = $params[1] ?? $field;
            $exceptValue = $params[2] ?? null;
            $exceptColumn = $params[3] ?? 'id';

            if (!$table) {
                // no se puede validar unique sin tabla indicada; considerar como error
                $db->closeConnection();
                return false;
            }

            $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = ?";
            $paramsSql = [$value];
            if ($exceptValue !== null && $exceptValue !== '') {
                $sql .= " AND `$exceptColumn` != ?";
                $paramsSql[] = $exceptValue;
            }
            $count = (int)$db->selectEspecial($sql, $paramsSql, 0);
            $db->closeConnection();
            return $count === 0;
        } catch (Exception $e) {
            // si hay problema con la BD, cerramos (si es posible) y devolvemos false
            try { if (isset($db)) $db->closeConnection(); } catch (Exception $_) {}
            return false;
        }
    }

    /**
     * Regla exists: exists:table,column
     */
    protected function validateExists(string $field, $value, array $params): bool
    {
        if ($this->isEmpty($value)) return false;

        try {
            $db = $this->getDb();
            $db->openConnection();

            $table = $params[0] ?? null;
            $column = $params[1] ?? $field;
            if (!$table) {
                $db->closeConnection();
                return false;
            }

            $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = ?";
            $count = (int)$db->selectEspecial($sql, [$value], 0);
            $db->closeConnection();
            return $count > 0;
        } catch (Exception $e) {
            try { if (isset($db)) $db->closeConnection(); } catch (Exception $_) {}
            return false;
        }
    }

    // Métodos directos para reglas específicas (si se necesita lógica más compleja)
    protected function validateRequired(string $field, $value, array $params): bool
    {
        return $this->applyGenericRule('required', $field, $value, $params);
    }
}