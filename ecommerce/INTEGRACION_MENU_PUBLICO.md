# 🌐 Integración del Menú Público Dinámico

## Para Usar el Menú Público Dinámico

### OPCIÓN 1: Mantener menú hardcodeado (SIN cambios)
El menú público seguirá funcionando como siempre, hardcodeado en `includes/header.php`.

**Archivo:** `/ecommerce/includes/header.php`  
**Líneas:** ~460-507

El menú sigue siendo igual. Solo pueden configurarlo desde aquí.

---

### OPCIÓN 2: Usar menú dinámico (RECOMENDADO)

**Paso 1:** Agregar al inicio de `includes/header.php` (después de `require_once __DIR__ . '/cache.php'`):

```php
require_once __DIR__ . '/menu_publico_helper.php';
```

**Paso 2:** Modificar la sección del menú en `includes/header.php` (líneas ~460-507):

ANTES (Menú hardcodeado):
```html
<ul class="navbar-nav">
  <li class="nav-item">
    <a class="nav-link" href="index.php">Inicio</a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="tienda.php">Tienda</a>
  </li>
  <!-- más items... -->
</ul>
```

DESPUÉS (Menú dinámico):
```php
<ul class="navbar-nav">
  <?php if ($USE_DYNAMIC_PUBLIC_MENU): ?>
    <?php
    $menu_items = render_menu_publico_dinamico($pdo);
    if ($menu_items):
      foreach ($menu_items as $item):
    ?>
      <li class="nav-item">
        <a class="nav-link" href="<?= htmlspecialchars($item['url'] ?? '#') ?>">
          <?php if ($item['icono']): ?>
            <i class="<?= htmlspecialchars($item['icono']) ?>"></i>
          <?php endif; ?>
          <?= htmlspecialchars($item['titulo']) ?>
        </a>
      </li>
    <?php
      endforeach;
    endif;
    ?>
  <?php else: ?>
    <!-- MENÚ HARDCODEADO DE FALLBACK -->
    <li class="nav-item">
      <a class="nav-link" href="index.php">Inicio</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="tienda.php">Tienda</a>
    </li>
    <!-- ... resto del menú antiguo ... -->
  <?php endif; ?>
  
  <!-- Carrito (sigue igual) -->
  <li class="nav-item">
    <a class="nav-link position-relative" href="carrito.php">
       Carrito
      <?php if (!empty($_SESSION['carrito']) && count($_SESSION['carrito']) > 0): ?>
        <span class="badge bg-danger position-absolute top-0 start-100 translate-middle">
          <?= count($_SESSION['carrito']) ?>
        </span>
      <?php endif; ?>
    </a>
  </li>
  
  <!-- Cuenta/Ingresar (sigue igual) -->
  <!-- ... -->
</ul>
```

---

## URLs Rápidas

**Administrar menú público:**
```
/ecommerce/admin/menu_publico_configuracion.php
```

**Administrar menú admin:**
```
/ecommerce/admin/menu_configuracion.php
```

---

## De Dónde Es Mejor No Tocar

✅ El carrito debe seguir siendo dinámico  
✅ La cuenta/ingresar debe seguir siendo dinámico  
✅ Solo configurar items de navegación principal

---

## Base de Datos

**Tabla:** `ecommerce_menu_publico`

```sql
SELECT * FROM ecommerce_menu_publico WHERE padre_id IS NULL ORDER BY orden;
```

Muestra los items del menú público.

---

*Integración completa - Julio 2024*
