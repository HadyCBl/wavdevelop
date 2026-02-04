# 🚀 Configuración de Eloquent ORM

## ✅ Estado: Instalado y Configurado

Eloquent ORM (Illuminate Database) está configurado y **coexiste** con `DatabaseAdapter` para permitir migración gradual.

---

## 📦 Conexiones Disponibles

### 1. **Conexión `default`** (Principal)
- Base de datos: `$_ENV['DDBB_NAME']`
- Usa esta para la mayoría de operaciones

### 2. **Conexión `general`** (Secundaria)
- Base de datos: `$_ENV['DDBB_NAME_GENERAL']`
- Para datos generales/compartidos

---

## 🎯 Cómo Usar

### **Opción 1: Query Builder (Sin modelo)**

```php
use Illuminate\Database\Capsule\Manager as DB;

// SELECT simple
$users = DB::table('usuarios')->get();

// WHERE
$user = DB::table('usuarios')->where('id', 1)->first();

// JOIN
$data = DB::table('creditos')
    ->join('clientes', 'creditos.cliente_id', '=', 'clientes.id')
    ->select('creditos.*', 'clientes.nombre')
    ->get();

// INSERT
DB::table('usuarios')->insert([
    'nombre' => 'Juan',
    'email' => 'juan@example.com'
]);

// UPDATE
DB::table('usuarios')->where('id', 1)->update(['nombre' => 'Pedro']);

// DELETE
DB::table('usuarios')->where('id', 1)->delete();

// Usar conexión 'general'
DB::connection('general')->table('config')->get();
```

---

### **Opción 2: Modelos Eloquent (Recomendado)**

#### 1. Crear un modelo:

```php
<?php

namespace Micro\Models;

class Cliente extends BaseModel
{
    protected $table = 'clientes';
    protected $primaryKey = 'id';
    
    // Si la tabla NO tiene timestamps (created_at, updated_at)
    public $timestamps = false;
    
    // Campos asignables en masa
    protected $fillable = [
        'codigo_cliente',
        'nombre',
        'identificacion'
    ];

    // Relación: Un cliente tiene muchos créditos
    public function creditos()
    {
        return $this->hasMany(Credito::class, 'cliente_id');
    }
}
```

#### 2. Usar el modelo:

```php
use Micro\Models\Clientes\Cliente;

// Obtener todos
$clientes = Cliente::all();

// Buscar por ID
$cliente = Cliente::find(1);

// Buscar por condición
$cliente = Cliente::where('codigo_cliente', 'C001')->first();

// Con relaciones
$cliente = Cliente::with('creditos')->find(1);

// Crear
$cliente = Cliente::create([
    'codigo_cliente' => 'C100',
    'nombre' => 'Juan Pérez',
    'identificacion' => '12345678'
]);

// Actualizar
$cliente->nombre = 'Pedro Gómez';
$cliente->save();

// Eliminar
$cliente->delete();
```

---

## 🔄 Migración Gradual

### **Mantener DatabaseAdapter (Actual)**

```php
// En controladores existentes - NO TOCAR
$database = new DatabaseAdapter();
$results = $database->consultar($query);
```

### **Usar Eloquent en nuevos controladores**

```php
use Illuminate\Database\Capsule\Manager as DB;
use Micro\Models\Clientes\Cliente;

class NuevoController
{
    public function index()
    {
        // Usando Query Builder
        $data = DB::table('clientes')->get();
        
        // O usando Modelo
        $clientes = Cliente::all();
        
        return json_encode($clientes);
    }
}
```

---

## 📚 Ejemplos de Controladores con Eloquent

### **Ejemplo: CuentasController con Eloquent**

```php
<?php

namespace Micro\Controllers\Seguros;

use Illuminate\Database\Capsule\Manager as DB;

class CuentasController
{
    public function index()
    {
        $cuentas = DB::table('asegurado_cuenta as ac')
            ->join('asegurado_servicio as aser', 'ac.servicio_id', '=', 'aser.id')
            ->select(
                'ac.id',
                'ac.fecha_inicio',
                'ac.observaciones',
                'ac.estado',
                'aser.nombre AS servicio_nombre',
                'aser.costo AS servicio_costo'
            )
            ->where('ac.cliente_id', '=', $clienteId)
            ->get();

        return json_encode($cuentas);
    }
}
```

---

## 🎨 Ventajas de Eloquent

✅ **Sintaxis clara y legible**  
✅ **Relaciones entre modelos**  
✅ **Query Builder potente**  
✅ **Protección contra SQL Injection automática**  
✅ **Eventos y Observers**  
✅ **Soft Deletes**  
✅ **Compatible con Laravel**  

---

## ⚙️ Configuración Técnica

- **Archivo config**: `includes/Config/eloquent.php`
- **Modelo base**: `app/Models/BaseModel.php`
- **Cargado en**: `includes/Config/config.php`
- **Compatible con**: DatabaseAdapter (coexisten sin conflicto)

---

## 🔍 Debugging

```php
// Ver última query ejecutada
DB::connection()->enableQueryLog();
$data = DB::table('usuarios')->get();
dd(DB::connection()->getQueryLog());

// Raw query
$users = DB::select('SELECT * FROM usuarios WHERE id = ?', [1]);
```

---

## 📖 Documentación Oficial

- [Laravel Eloquent ORM](https://laravel.com/docs/11.x/eloquent)
- [Query Builder](https://laravel.com/docs/11.x/queries)
- [Database Basics](https://laravel.com/docs/11.x/database)
