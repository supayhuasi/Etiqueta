# Corrección de Rutas de Navegación - Tercera Solución

## Problema Reportado
La navegación entre módulos estaba rota. Cuando se hacía clic en un enlace del menú desde un módulo (ej: sueldos) a otro (ej: asistencias), la URL resultante era incorrecta:
- **Actual (incorrecto):** `/ecommerce/admin/sueldos/asistencias/asistencias.php`
- **Esperada:** `/ecommerce/admin/asistencias/asistencias.php`

## Causa Raíz
El navegador resuelve las rutas relativas desde la carpeta actual del archivo.
- Un enlace `href="asistencias/asistencias.php"` desde `/ecommerce/admin/sueldos/sueldos.php` se resolvería como `/ecommerce/admin/sueldos/asistencias/asistencias.php`

## Soluciones Anteriores (Fallidas)
1. **Intento 1:** Calcular `$relative_to_admin` (número de `../` necesarios)
   - Problema: La matemática de contar barras fue incorrecta
   - Resultado: Still broken - "sigue pasando lo mismo"

## Solución Actual (Implementada)
**Cambio estratégico: Usar URLs absolutas en lugar de relativas**

### Cambios en `/ecommerce/admin/includes/header.php`:

1. **Líneas 16-25:** Se agregó variable de URL absoluta
   ```php
   // Usar URL absoluta desde el servidor para evitar problemas con rutas relativas
   $admin_url = '/ecommerce/admin/';  // URL absoluta desde raíz del servidor
   ```

2. **Líneas 99-135:** Se reemplazaron todos los enlaces del menú
   - **Antes:** `href="<?= $relative_to_admin ?>modulo/archivo.php"`
   - **Después:** `href="<?= $admin_url ?>modulo/archivo.php"`

### Ejemplos de Cambios:
```php
<!-- ANTES (relativo) -->
<a href="<?= $relative_to_admin ?>sueldos/sueldos.php">Sueldos</a>

<!-- DESPUÉS (absoluto) -->
<a href="<?= $admin_url ?>sueldos/sueldos.php">Sueldos</a>
```

Genera HTML:
```html
<a href="/ecommerce/admin/sueldos/sueldos.php">Sueldos</a>
```

## Por Qué Funciona Esta Solución

Las URLs que comienzan con `/` son **absolutas desde la raíz del servidor HTTP**.

- El navegador interpreta `/ecommerce/admin/sueldos/sueldos.php` de la misma manera independientemente de donde se encuentre el archivo actual
- No importa si estás en `/ecommerce/admin/sueldos/sueldos.php` o en `/ecommerce/admin/gastos/gastos_crear.php`
- La URL siempre será correcta porque es absoluta

## Enlaces Modificados
Se actualizaron todos los enlaces en el menú de navegación:
- ✅ Tablero de Control
- ✅ Todas las categorías del catálogo
- ✅ Empresa (empresa.php, mp_config.php, inventario, pedidos, órdenes, facturación, cotizaciones)
- ✅ Compras (proveedores, compras, ajustes)
- ✅ **Recursos Humanos (sueldos, plantillas, asistencias)** ← CRÍTICO
- ✅ **Finanzas (cheques, gastos)** ← CRÍTICO

## Cómo Verificar
1. Accede a: `http://tu-sitio.com/ecommerce/admin/sueldos/sueldos.php`
2. Abre DevTools (F12) → pestaña **Network**
3. Haz clic en el enlace "Asistencias"
4. Verifica que la URL en la barra es: `/ecommerce/admin/asistencias/asistencias.php`

## Archivos Modificados
- `/ecommerce/admin/includes/header.php` - Reemplazadas todas las referencias de rutas relativas por URLs absolutas

## Archivos de Soporte Creados (para diagnóstico)
- `/ecommerce/admin/test_navigation.php` - Test de navegación
- `/ecommerce/admin/diagnostico_rutas.txt` - Documentación técnica
- `/ecommerce/admin/verificar_urls.html` - Verificador visual de URLs

## Ventajas de Esta Solución
✅ Simple y directa
✅ No depende de cálculos matemáticos de rutas
✅ Inmune a problemas de resolución relativa
✅ Compatible con todos los navegadores
✅ Fácil de mantener y depurar
✅ No requiere cambios en la lógica de la aplicación
