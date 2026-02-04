# 🔧 Correcciones Realizadas - Migración de Módulos

## Problemas Encontrados y Solucionados

### 1. **Require duplicado de config.php**
**Problema:** Los archivos del módulo (ej: asistencias.php) tenían:
```php
require '../../config.php';
require '../includes/header.php';
```
Y el header también cargaba config.php, causando un duplicado.

**Solución:** Eliminado el `require '../../config.php'` de todos los archivos de módulos. El header ahora es la única fuente de config.php.

**Archivos modificados:**
- `ecommerce/admin/asistencias/*.php` (excepto index.php)
- `ecommerce/admin/sueldos/*.php` (excepto index.php)
- `ecommerce/admin/cheques/*.php` (excepto index.php)
- `ecommerce/admin/gastos/*.php` (excepto index.php)

---

### 2. **Rutas relativas incorrectas en header.php**
**Problema:** El header usaba `dirname()` incorrecto:
```php
$base_path = dirname(dirname(dirname(__FILE__)));  // ❌ 3 niveles = incorrecto
```

Cuando `__FILE__` = `/path/to/ecommerce/admin/includes/header.php`, necesita 4 niveles para llegar a `/path/to/config.php`.

**Solución:** Corregido a:
```php
$base_path = dirname(dirname(dirname(dirname(__FILE__))));  // ✓ 4 niveles = correcto
```

---

### 3. **Redirecciones hardcodeadas en header**
**Problema:** El header tenía rutas fijas:
```php
header("Location: ../../auth/login.php");
<a href="../../cambiar_clave.php">
<a href="../../index.php">
```

Estas rutas no funcionaban correctamente desde subdirectorios como `/ecommerce/admin/asistencias/`.

**Solución:** Crear variable dinámica `$relative_root` que se calcula según la profundidad actual:
```php
$current_dir = substr(str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', realpath(dirname($_SERVER['PHP_SELF']))), 1);
$depth = substr_count($current_dir, '/');
$relative_root = str_repeat('../', $depth);
```

Ahora todos los enlaces usan: `href="<?= $relative_root ?>ruta/archivo.php"`

---

### 4. **Enlaces del menú en header**
**Cambios realizados:**
- Actualizar toda referencia `../../` a `<?= $relative_root ?>`
- Ejemplos:
  - `../../index.php` → `<?= $relative_root ?>index.php` (Inicio Principal)
  - `../../auth/logout.php` → `<?= $relative_root ?>auth/logout.php` (Salir)
  - `../../cambiar_clave.php` → `<?= $relative_root ?>cambiar_clave.php`
  - `../index.php` → `<?= $relative_root ?>ecommerce/index.php` (Ir a Tienda)

---

## ✅ Lo que Ahora Funciona

1. **Carga correcta de config.php**
   - Desde cualquier ubicación (admin, admin/asistencias/, admin/sueldos/, etc.)
   - Única carga (no duplicada)

2. **Redirecciones correctas**
   - Login: `header("Location: " . $relative_root . "auth/login.php")`
   - Funciona desde cualquier profundidad

3. **Menú del header funciona desde cualquier módulo**
   - Sueldos → `ecommerce/admin/sueldos/sueldos.php` ✓
   - Asistencias → `ecommerce/admin/asistencias/asistencias.php` ✓
   - Cheques → `ecommerce/admin/cheques/cheques.php` ✓
   - Gastos → `ecommerce/admin/gastos/gastos.php` ✓

4. **Enlaces al sistema principal funcionan**
   - Inicio Principal → `/index.php` ✓
   - Usuarios → `/usuarios_lista.php` ✓
   - Y otros

---

## 🧪 Cómo Verificar

Puedes usar el archivo de diagnóstico para verificar que todo funciona:

```
http://sistema.tucuroller.com.ar/ecommerce/diagnostico.php
```

Este archivo verifica:
- ✓ Rutas de archivos
- ✓ Carga de config
- ✓ Conexión PDO
- ✓ Tablas de base de datos
- ✓ Sesión activa
- ✓ Archivos de módulos migrados

---

## 📝 Archivos Modificados

### Header (Principal):
- `ecommerce/admin/includes/header.php` - Rutas dinámicas

### Módulos (Eliminado duplicado config):
- `ecommerce/admin/asistencias/*.php` - Excepto index.php
- `ecommerce/admin/sueldos/*.php` - Excepto index.php
- `ecommerce/admin/cheques/*.php` - Excepto index.php
- `ecommerce/admin/gastos/*.php` - Excepto index.php

### Nuevos Archivos:
- `ecommerce/test_config.php` - Test básico
- `ecommerce/diagnostico.php` - Diagnóstico completo

---

## 🚀 URLs Ahora Funcionales

```
✅ http://sistema.tucuroller.com.ar/ecommerce/admin/asistencias/asistencias.php
✅ http://sistema.tucuroller.com.ar/ecommerce/admin/sueldos/sueldos.php
✅ http://sistema.tucuroller.com.ar/ecommerce/admin/cheques/cheques.php
✅ http://sistema.tucuroller.com.ar/ecommerce/admin/gastos/gastos.php
✅ http://sistema.tucuroller.com.ar/ecommerce/admin/index.php
✅ http://sistema.tucuroller.com.ar/ecommerce/admin/ (con redirección)
```

---

## 🔍 Si Sigue Sin Funcionar

1. Verifica que estés logueado (sesión activa)
2. Ejecuta diagnostico.php para ver qué falla específicamente
3. Revisa los logs del servidor (error.log)
4. Verifica que las tablas existan (ejecuta setup si es necesario)

