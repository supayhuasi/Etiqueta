# 📋 Módulo de Configuración del Menú del Ecommerce

## Descripción General

Este módulo permite configurar dinámicamente el menú del ecommerce sin necesidad de editar código. Puedes:

✅ **Agregar, editar y eliminar secciones del menú**  
✅ **Agregar, editar y eliminar items dentro de cada sección**  
✅ **Controlar permisos de acceso por sección**  
✅ **Activar/desactivar opciones sin eliminarlas**  
✅ **Reordenar secciones e items**  
✅ **Ver una vista unificada de clientes (Web + Cotización)**

---

## Instalación

### Paso 1: Ejecutar el Setup

1. Accede a la siguiente URL en tu navegador:
   ```
   /ecommerce/setup_menu_configuracion.php
   ```

2. El setup creará automáticamente las tablas necesarias:
   - `ecommerce_menu_configuracion` - Secciones del menú
   - `ecommerce_menu_items` - Items dentro de cada sección

3. Se crearán configuraciones por defecto con todas las secciones actuales.

---

## Uso

### Acceso a la Configuración

**Solo administradores** pueden acceder a:

- **Configuración de Secciones:** `/ecommerce/admin/menu_configuracion.php`
- **Configuración de Items:** `/ecommerce/admin/menu_items.php?seccion_id=ID`
- **Clientes Unificados:** `/ecommerce/admin/clientes_unificado.php`

### Estructura de Datos

```
ecommerce_menu_configuracion (Secciones)
├── id (INT PRIMARY KEY)
├── seccion (VARCHAR) - Clave única: 'ventas', 'catalogo', etc.
├── icono (VARCHAR) - Ícono de Bootstrap Icons: 'bi bi-cart-check'
├── label (VARCHAR) - Etiqueta visible: 'Ventas'
├── titulo (VARCHAR) - Tooltip title
├── permisos (JSON) - Array de permisos requeridos
├── orden (INT) - Orden de aparición
├── activo (BOOLEAN) - Mostrar/ocultar sección
└── Timestamps

ecommerce_menu_items (Items)
├── id (INT PRIMARY KEY)
├── seccion_id (INT FOREIGN KEY)
├── titulo (VARCHAR) - 'Productos'
├── icono (VARCHAR) - 'bi bi-box'
├── url (VARCHAR) - '/ecommerce/admin/productos.php'
├── permiso (VARCHAR) - 'productos' (requerido)
├── orden (INT) - Orden dentro de la sección
├── activo (BOOLEAN) - Mostrar/ocultar item
└── Timestamps
```

---

## Funcionalidades Principales

### 1. Configuración de Secciones

**Archivo:** `menu_configuracion.php`

Aquí puedes:
- ✏️ Ver todas las secciones del menú
- ➕ Agregar nuevas secciones
- 🗑️ Eliminar secciones (se eliminan también sus items)
- 👁️ Activar/desactivar secciones
- 📍 Ver cantidad de items en cada sección

**Ejemplo: Agregar una sección personalizada**
```
Clave: reporte_ventas
Label: Reportes de Ventas
Ícono: bi bi-bar-chart-line
Título: Ver todos los reportes
```

### 2. Configuración de Items

**Archivo:** `menu_items.php`

Aquí puedes:
- ➕ Agregar items a una sección
- 🗑️ Eliminar items
- 👁️ Activar/desactivar items
- 📍 Ver orden de items

**Campos de cada item:**
- **Título:** Lo que se ve en el menú
- **URL:** Ruta del archivo/página
- **Ícono:** Ícono de Bootstrap Icons
- **Permiso:** Clave de permiso requerida (ej: 'productos')

**Ejemplo: Item de Productos**
```
Título: Productos
URL: /ecommerce/admin/productos.php
Ícono: bi bi-box
Permiso: productos
```

### 3. Clientes Unificados

**Archivo:** `clientes_unificado.php`

Esta página reemplaza la necesidad de ir a dos lugares diferentes:
- **Antes:** 
  - Clientes Web → `/ecommerce/admin/clientes_web.php`
  - Clientes Cotización → `/ecommerce/admin/cotizacion_clientes.php`

- **Ahora:** Una sola página con tabs para:
  - Todos los clientes
  - Solo Clientes Web
  - Solo Clientes Cotización

**Características:**
- 🔄 Cambiar entre vistas sin recargar
- 📊 Ver estadísticas de cada tipo
- 🏷️ Identificar tipo de cliente por color/badge
- ⚙️ Acceso a operaciones específicas (editar, ver detalles)

---

## URLs de Ícones

El módulo usa **Bootstrap Icons**. Aquí tienes algunos ejemplos:

| Ícono | Código | Uso |
|-------|--------|-----|
| 🏠 Casa | `bi bi-house-door` | Inicio/Dashboard |
| 📦 Caja | `bi bi-box` | Productos |
| 🛒 Carrito | `bi bi-cart-check` | Ventas/Pedidos |
| 👥 Personas | `bi bi-people` | Clientes/usuarios |
| ⚙️ Engranaje | `bi bi-gear-fill` | Configuración |
| 💰 Dinero | `bi bi-cash-stack` | Finanzas |
| 📊 Gráfico | `bi bi-bar-chart` | Reportes |

**Ver todos:** https://icons.getbootstrap.com/

---

## Casos de Uso

### Caso 1: Agregar a un rol acceso solo a ciertos módulos

1. En `menu_configuracion.php`, puedes ver los permisos requeridos por cada sección
2. Los permisos se definen en `$role_permissions` del `header.php`
3. El menú dinámico respetar estos permisos automáticamente

### Caso 2: Ocultar temporalmente una sección

1. Ir a `menu_configuracion.php`
2. Hacer click en el ícono 👁️ (Desactivar)
3. La sección desaparece del menú sin ser eliminada
4. Se puede reactivar en cualquier momento

### Caso 3: Crear menú personalizado para un rol

1. Crear items con permisos específicos
2. En la tabla `ecommerce_menu_items`, agregar el permiso requerido
3. En `header.php`, agregar esos permisos al rol en `$role_permissions`
4. El menú se mostrará solo para usuarios con ese rol

---

## Cambios en el Sistema

### Cambios principales:

1. **Nueva tabla de menú dinámico** 
   - Ya no es hardcodeado en `header.php`
   - Se carga de la BD

2. **Menú unificado de clientes**
   - Nueva página: `clientes_unificado.php`
   - Reemplaza la necesidad de dos páginas diferentes
   - Mismo en el menú del admin

3. **El header.php sigue funcionando igual**
   - Si las tablas no existen, usa el menú hardcodeado (fallback)
   - Transición suave sin romper nada existente

---

## Base de Datos - Queries útiles

### Ver todas las secciones
```sql
SELECT * FROM ecommerce_menu_configuracion ORDER BY orden;
```

### Ver todos los items de una sección
```sql
SELECT * FROM ecommerce_menu_items WHERE seccion_id = 3 ORDER BY orden;
```

### Desactivar una sección
```sql
UPDATE ecommerce_menu_configuracion SET activo = 0 WHERE seccion = 'cotizaciones';
```

### Acceder a un item específico
```sql
SELECT * FROM ecommerce_menu_items WHERE permiso = 'productos';
```

---

## Seguridad

✅ **CSRF Protection:** Todos los formularios usan tokens CSRF  
✅ **Permisos:** Solo administradores pueden configurar el menú  
✅ **Sanitizado:** Todos los datos se escapa con `htmlspecialchars()`  
✅ **Prepared Statements:** Se usan para todas las queries  

---

## Troubleshooting

### El menú no aparece configurado
1. Ejecutar `/ecommerce/setup_menu_configuracion.php`
2. Las tablas deben existir: `ecommerce_menu_configuracion` y `ecommerce_menu_items`

### Solo ven menú parcial
1. Verificar permisos del usuario en `$role_permissions` de `header.php`
2. Verificar que los items tengan el permiso asignado correctamente

### Quiero volver al menú hardcodeado
1. Renombrar o eliminar las tablas de menú
2. El header.php detectará que no existen y usará el menú antiguo

---

## Próximos Pasos Recomendados

1. ✅ Ejecutar el setup: `/ecommerce/setup_menu_configuracion.php`
2. ✅ Ir a `menu_configuracion.php` para revisar las secciones
3. ✅ Personalizar según necesidades (agregar/editar items)
4. ✅ Acceder a `clientes_unificado.php` en lugar de dos páginas diferentes
5. ✅ Documentar tu configuración de menú personalizada

---

**Módulo desarrollado:** 2024  
**Última actualización:** Julio 2024
