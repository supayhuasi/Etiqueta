# 📍 Ubicación del Módulo Flujo de Caja

## ✅ Módulo Movido a Ecommerce/Admin

El módulo de Flujo de Caja ahora está ubicado en:
```
/ecommerce/admin/
```

## 📂 Archivos en la Nueva Ubicación

Todos los archivos están en: `/ecommerce/admin/`

```
✓ setup_flujo_caja.php
✓ flujo_caja.php                    (Dashboard principal)
✓ flujo_caja_ingreso.php            (Registrar ingresos)
✓ flujo_caja_egreso.php             (Registrar egresos)
✓ flujo_caja_editar.php             (Editar transacciones)
✓ flujo_caja_eliminar.php           (Eliminar transacciones)
✓ flujo_caja_reportes.php           (Reportes)
✓ pagos_sueldos_parciales.php       (Gestión pagos parciales)
✓ flujo_caja_importar.php           (Importar datos históricos)
```

## 🚀 Acceso al Módulo

### URLs Actualizadas:

```
Setup:     http://tu-sistema.com/ecommerce/admin/setup_flujo_caja.php
Dashboard: http://tu-sistema.com/ecommerce/admin/flujo_caja.php
Reportes:  http://tu-sistema.com/ecommerce/admin/flujo_caja_reportes.php
```

## 📋 Instalación Actualizada

### 1. Crear Tablas (1 minuto)
```
http://tu-sistema.com/ecommerce/admin/setup_flujo_caja.php
```

### 2. Agregar al Menú (1 minuto)

En `/ecommerce/admin/includes/header.php` o el menú del ecommerce, agrega:

```html
<!-- Dentro de la sección de Admin/Finanzas -->
<li class="nav-item">
    <a class="nav-link" href="flujo_caja.php">
        <i class="bi bi-cash-stack"></i> Flujo de Caja
    </a>
</li>

<!-- O si usas submenu: -->
<li class="nav-item">
    <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#flujoCajaMenu">
        <i class="bi bi-cash-stack"></i> Flujo de Caja
    </a>
    <ul class="collapse" id="flujoCajaMenu">
        <li><a href="flujo_caja.php">Dashboard</a></li>
        <li><a href="flujo_caja_ingreso.php">Nuevo Ingreso</a></li>
        <li><a href="flujo_caja_egreso.php">Nuevo Egreso</a></li>
        <li><a href="pagos_sueldos_parciales.php">Pagos de Sueldos</a></li>
        <li><a href="flujo_caja_reportes.php">Reportes</a></li>
    </ul>
</li>
```

### 3. Importar Datos (Opcional)
```
http://tu-sistema.com/ecommerce/admin/flujo_caja_importar.php
```

## ✅ Rutas Actualizadas

Todos los archivos ahora usan:
- `require '../../config.php';` (en lugar de `require 'config.php';`)
- `require '../../auth/check.php';` (en lugar de `require 'auth/check.php';`)
- `<link href="../../assets/bootstrap.min.css">` (ruta correcta a assets)
- `require 'includes/header.php';` (header del admin de ecommerce)

## 🎯 Características Mantenidas

Todo funciona igual:
- ✅ Dashboard con ingresos/egresos/saldo
- ✅ Pagos parciales de sueldos con fechas
- ✅ Reportes detallados
- ✅ Importación de datos
- ✅ Edición y eliminación
- ✅ 3 tablas en base de datos

## 📝 Notas Importantes

1. **Base de Datos**: Las tablas siguen siendo las mismas:
   - `flujo_caja`
   - `pagos_sueldos_parciales`
   - `flujo_caja_resumen`

2. **Menú**: Agrega los enlaces en el menú de ecommerce/admin

3. **Permisos**: Asegúrate de que los usuarios autorizados puedan acceder a `/ecommerce/admin/`

4. **Enlaces Internos**: Todos los enlaces entre páginas del flujo de caja ya están correctos (usan rutas relativas)

## 🔍 Verificación Rápida

Para verificar que todo funciona:

```bash
# 1. Verifica que los archivos existan
ls -la /ruta/del/proyecto/ecommerce/admin/flujo_caja*.php

# 2. Ejecuta el setup
# Accede a: http://tu-sistema.com/ecommerce/admin/setup_flujo_caja.php

# 3. Accede al dashboard
# Accede a: http://tu-sistema.com/ecommerce/admin/flujo_caja.php
```

## 🎉 ¡Listo!

El módulo está completamente integrado en `/ecommerce/admin/` y listo para usar.

