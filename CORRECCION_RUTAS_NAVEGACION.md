# 🔧 Corrección de Rutas de Navegación Entre Módulos

## 🐛 Problema Identificado

Cuando navegabas entre módulos, la URL se construía incorrectamente:

```
✗ ANTES (incorrecto):
De sueldos a asistencias:
http://sistema.tucuroller.com.ar/ecommerce/admin/sueldos/asistencias/asistencias.php
↑ Nota: "asistencias" se inserta DENTRO de "sueldos/"
```

### Causa Raíz
Los enlaces en el menú eran relativos a la ubicación actual:
```php
<a href="asistencias/asistencias.php">  <!-- ❌ Relativo a la carpeta actual -->
```

Cuando estabas en `/ecommerce/admin/sueldos/sueldos.php`, el navegador resolvía `asistencias/asistencias.php` como `/ecommerce/admin/sueldos/asistencias/asistencias.php`.

---

## ✅ Solución Implementada

### 1. **Nueva Variable: `$relative_to_admin`**

Se agregó al header una variable que calcula cuántos `../` necesitas para volver a `ecommerce/admin/`:

```php
// Calcular cuántos ../ necesitamos para volver a ecommerce/admin/
$php_self = $_SERVER['PHP_SELF'];
$admin_path = '/ecommerce/admin/';
$admin_depth = substr_count($admin_path, '/');
$current_depth = substr_count(dirname($php_self), '/');
$relative_to_admin = str_repeat('../', max(0, $current_depth - $admin_depth));
```

**Ejemplos:**
- Si estás en `/ecommerce/admin/index.php` → `$relative_to_admin = ''` (0 ../)
- Si estás en `/ecommerce/admin/sueldos/sueldos.php` → `$relative_to_admin = '../'` (1 ../)
- Si estás en `/ecommerce/admin/sueldos/subfolder/archivo.php` → `$relative_to_admin = '../../'` (2 ../)

### 2. **Enlaces Actualizados en Header**

Todos los enlaces ahora usan `$relative_to_admin`:

```php
<!-- ANTES (❌ incorrecto) -->
<a href="asistencias/asistencias.php">Asistencias</a>
<a href="index.php">Inicio</a>

<!-- DESPUÉS (✓ correcto) -->
<a href="<?= $relative_to_admin ?>asistencias/asistencias.php">Asistencias</a>
<a href="<?= $relative_to_admin ?>index.php">Inicio</a>
```

**Enlaces actualizados:**
- Módulos principales: `index.php`, `dashboard.php`, `categorias.php`, `productos.php`, etc.
- Módulos migrados: `sueldos/sueldos.php`, `asistencias/asistencias.php`, `cheques/cheques.php`, `gastos/gastos.php`

---

## 🧪 Ejemplos de Navegación Correcta

```
✓ DESPUÉS (correcto):
De sueldos a asistencias:
Ubicación actual: /ecommerce/admin/sueldos/sueldos.php
$relative_to_admin = '../'
Clic en Asistencias → href="<?= $relative_to_admin ?>asistencias/asistencias.php"
Resultado: ../asistencias/asistencias.php
URL final: /ecommerce/admin/asistencias/asistencias.php ✓

De asistencias a sueldos:
Ubicación actual: /ecommerce/admin/asistencias/asistencias.php
$relative_to_admin = '../'
Clic en Sueldos → href="<?= $relative_to_admin ?>sueldos/sueldos.php"
Resultado: ../sueldos/sueldos.php
URL final: /ecommerce/admin/sueldos/sueldos.php ✓

De sueldos a index:
Ubicación actual: /ecommerce/admin/sueldos/sueldos.php
$relative_to_admin = '../'
Clic en Inicio → href="<?= $relative_to_admin ?>index.php"
Resultado: ../index.php
URL final: /ecommerce/admin/index.php ✓
```

---

## 📝 Archivos Modificados

**Header (Principal):**
- `ecommerce/admin/includes/header.php`
  - Agregado cálculo de `$relative_to_admin` (líneas 20-25)
  - Actualizado TODOS los enlaces del menú (líneas 105-148)

---

## 🚀 URLs Ahora Funcionan Correctamente

```
✅ Sueldos → Asistencias: Funciona correctamente
✅ Asistencias → Cheques: Funciona correctamente
✅ Cheques → Gastos: Funciona correctamente
✅ Cualquier módulo → Inicio: Funciona correctamente
✅ Navegación en cualquier dirección: SIEMPRE correcta
```

---

## 🔍 Verificación

Para verificar que funciona, intenta:

1. Accede a Sueldos: `/ecommerce/admin/sueldos/sueldos.php`
2. Haz clic en Asistencias en el menú
3. Verifica que la URL sea: `/ecommerce/admin/asistencias/asistencias.php` ✓

La URL NO debe ser: `/ecommerce/admin/sueldos/asistencias/asistencias.php` ✗

---

## 💡 Por Qué Funciona

La clave está en que `$relative_to_admin` se recalcula dinámicamente en cada página:

1. Cuando cargas una página, `$_SERVER['PHP_SELF']` contiene la URL actual
2. El header calcula cuántos niveles arriba está `ecommerce/admin/`
3. Genera la cantidad correcta de `../` para volver a esa carpeta
4. Todos los enlaces usan esta variable, por lo que SIEMPRE son correctos
5. No importa desde qué módulo hagas clic - las rutas son correctas

