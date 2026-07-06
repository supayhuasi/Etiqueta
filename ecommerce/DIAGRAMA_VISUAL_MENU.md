# 🗺️ DIAGRAMA VISUAL DEL MÓDULO DE MENÚ

## Flujo General del Sistema

```
┌─────────────────────────────────────────────────────────────────┐
│                    ADMINISTRADOR DEL ECOMMERCE                  │
│                        (rol = 'admin')                           │
└─────────────────────────────────────────────────────────────────┘
                              │
                ┌─────────────┼─────────────┐
                │             │             │
                ▼             ▼             ▼
        ┌──────────────┐┌──────────────┐┌──────────────┐
        │    SETUP     ││ CONFIGURAR   ││   VER DATOS  │
        │   INICIAL    ││    MENÚ      ││   UNIFICADO  │
        │──────────────││──────────────││──────────────│
        │ setup_menu_  ││ menu_config  ││ clientes_    │
        │ configuración││ uracion.php  ││ unificado.php│
        └──────────────┘└──────────────┘└──────────────┘
                │
                ▼
        ┌──────────────────────┐
        │  CREA TABLAS EN BD   │
        │  ✓ Secciones (8)     │
        │  ✓ Items (~30)       │
        └──────────────────────┘
```

---

## Flujo de Configuración del Menú

```
ADMIN accede a: /ecommerce/admin/menu_configuracion.php
│
├─ LISTAR SECCIONES
│  └─ SELECT * FROM ecommerce_menu_configuracion ORDER BY orden
│     └─ Obtiene: Dashboard, Catálogo, Empresa, Ventas, etc.
│
├─ AGREGAR SECCIÓN
│  └─ INSERT INTO ecommerce_menu_configuracion
│     ├─ seccion: (clave única)
│     ├─ label: (nombre visible)
│     ├─ icono: (bootstrap icon)
│     └─ permisos: (JSON array)
│
├─ DESACTIVAR SECCIÓN
│  └─ UPDATE ecommerce_menu_configuracion SET activo = 0
│     ➜ Sección se oculta del menú (SIN eliminar)
│
├─ ELIMINAR SECCIÓN
│  └─ DELETE FROM ecommerce_menu_configuracion WHERE id = ?
│     └─ CASCADA: también elimina todos sus items
│
└─ VER ITEMS DE SECCIÓN
   └─ Click en ✏️ → menu_items.php?seccion_id=3
      └─ Ir a: GESTIÓN DE ITEMS
```

---

## Flujo de Configuración de Items

```
ADMIN accede a: /ecommerce/admin/menu_items.php?seccion_id=3
│
├─ LISTAR ITEMS
│  └─ SELECT * FROM ecommerce_menu_items 
│     WHERE seccion_id = 3 ORDER BY orden
│     └─ Obtiene: Pedidos, Órdenes Prod., Instalaciones, etc.
│
├─ AGREGAR ITEM
│  └─ INSERT INTO ecommerce_menu_items
│     ├─ seccion_id: (FK a sección)
│     ├─ titulo: (nombre del item)
│     ├─ url: (ruta del archivo/página)
│     ├─ icono: (bootstrap icon)
│     ├─ permiso: (requerido para mostrar)
│     └─ orden: (posición en la sección)
│
├─ DESACTIVAR ITEM
│  └─ UPDATE ecommerce_menu_items SET activo = 0
│     ➜ Item se oculta del menú (SIN eliminar)
│
└─ ELIMINAR ITEM
   └─ DELETE FROM ecommerce_menu_items WHERE id = ?
      └─ Se elimina solo ese item (la sección sigue intacta)
```

---

## Flujo de Clientes Unificados

```
USUARIO accede a: /ecommerce/admin/clientes_unificado.php
│
├─ DATOS INICIALES
│  ├─ Clientes Web:
│  │  └─ SELECT * FROM ecommerce_clientes
│  │     ├─ id, nombre, email, estado, provider
│  │     └─ Típicamente: 45 clientes
│  │
│  └─ Clientes Cotización:
│     └─ SELECT * FROM ecommerce_cotizacion_clientes
│        ├─ id, nombre
│        └─ Típicamente: 12 clientes
│
├─ VISTA "TODOS" (Tab activo por defecto)
│  └─ Combina: Clientes Web (45) + Cotización (12) = 57 total
│     ├─ Muestra colores diferentes por tipo
│     ├─ Badge "Web" vs "Cotización"
│     └─ Estadísticas: 45 web, 12 cotización, 57 total
│
├─ VISTA "WEB" (Click en tab)
│  └─ Filtra: Solo ecommerce_clientes
│     ├─ Muestra: nombre, email, proveedor, estado, fecha
│     ├─ Opciones: Editar (clientes_web_editar.php)
│     └─ Opciones: Ver detalles (clientes_web_detalle.php)
│
├─ VISTA "COTIZACIÓN" (Click en tab)
│  └─ Filtra: Solo ecommerce_cotizacion_clientes
│     ├─ Muestra: nombre
│     └─ Opciones: Editar (cotizacion_clientes_editar.php)
│
└─ TABLA UNIFICADA
   └─ Una sola tabla que se filtra por vista
      ├─ Menos confusión
      ├─ Estadísticas combinadas
      └─ No necesita ir a dos páginas diferentes
```

---

## Estructura de Datos - Visualización

### Base de Datos

```
DATABASE: tucuroller_produccion
│
├─ ecommerce_menu_configuracion (NUEVA)
│  ├─ id (PK)
│  ├─ seccion (VARCHAR, UNIQUE) ────────────────────┐
│  │  Valores: dashboard, catalogo, empresa,        │
│  │  ventas, compras, rrhh, finanzas, sistema      │
│  │                                                  │
│  ├─ icono (VARCHAR)                               │
│  │  Ej: "bi bi-cart-check"                        │
│  │                                                  │
│  ├─ label (VARCHAR)                               │
│  │  Ej: "Ventas"                                  │
│  │                                                  │
│  ├─ titulo (VARCHAR)                              │
│  │  Ej: "Gestión de ventas y pedidos"             │
│  │                                                  │
│  ├─ permisos (JSON)                               │
│  │  Valores: ["pedidos", "clientes_web", ...]    │
│  │                                                  │
│  ├─ orden (INT)                                   │
│  │  Posición en el menú: 1, 2, 3, ...             │
│  │                                                  │
│  └─ activo (BOOLEAN) ─────────────────────────────┐
│                                                    │
├─ ecommerce_menu_items (NUEVA)                    │
│  ├─ id (PK)                                       │
│  ├─ seccion_id (FK) ◄────────────────────────────┘
│  │  ref: ecommerce_menu_configuracion.id
│  │
│  ├─ titulo (VARCHAR)
│  │  Ej: "Productos"
│  │
│  ├─ icono (VARCHAR)
│  │  Ej: "bi bi-box"
│  │
│  ├─ url (VARCHAR)
│  │  Ej: "/ecommerce/admin/productos.php"
│  │
│  ├─ permiso (VARCHAR)
│  │  Ej: "productos"
│  │
│  ├─ orden (INT)
│  │  Orden dentro de la sección
│  │
│  └─ activo (BOOLEAN)
│
├─ ecommerce_clientes (EXISTENTE)
│  ├─ id, nombre, email, estado, provider, ...
│  └─ Clientes Web del ecommerce
│
└─ ecommerce_cotizacion_clientes (EXISTENTE)
   ├─ id, nombre, ...
   └─ Clientes de cotización
```

---

## Relaciones de Tablas

```
ecommerce_menu_configuracion (Secciones)
          │
          │ 1 : N (Uno a Muchos)
          │
          ▼
ecommerce_menu_items (Items)

Ejemplo:
┌─────────────────────────────────┐
│ Sección: "Ventas" (id=4)         │
│ Icono: bi bi-cart-check          │
│ Label: Ventas                    │
└─────────────────────────────────┘
          │
    ┌─────┴─────┬─────────┬──────────┐
    │           │         │          │
    ▼           ▼         ▼          ▼
┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐
│Pedidos │ │Órdenes │ │Instala │ │CLIENTES│
│        │ │Producció│ │ciones  │ │UNIFICADO
│product │ │n       │ │        │ │ (NUEVO)
│os.php  │ │ordenes │ │instala │ │clientes
│        │ │prod.php│ │ciones  │ │unificado
│        │ │        │ │.php    │ │.php
└────────┘ └────────┘ └────────┘ └────────┘
```

---

## Matriz de Permisos

```
Rol: ADMIN
├─ Acceso: Todas las secciones (*)
├─ Menú completo: 8 secciones, ~30 items
└─ Puede: Crear, editar, eliminar, activar/desactivar

Rol: VENDEDOR
├─ Acceso: dashboard, pedidos, cotizaciones, clientes
├─ Menú: Solo esas secciones se muestran
└─ Puede: Ver, filtrar, acciones permitidas

Rol: OPERARIO
├─ Acceso: dashboard, ordenes_produccion, instalaciones
├─ Menú: Solo esas secciones se muestran
└─ Puede: Ver, actualizar estado

Rol: REVENDEDOR
├─ Acceso: dashboard, cotizaciones, pedidos, clientes
├─ Menú: Limitado a su rol
└─ Puede: Ver sus cotizaciones y pedidos
```

---

## Flujo de Verificación de Permisos

```
Usuario Admin accede a /ecommerce/admin/index.php
│
├─ El header.php renderiza el menú
│
├─ Para cada SECCIÓN:
│  ├─ Obtiene: permisos JSON: ["pedidos", "clientes_web", ...]
│  └─ Pregunta: El usuario tiene acceso a alguno?
│     ├─ SÍ → Muestra la SECCIÓN
│     └─ NO → Omite la SECCIÓN
│
└─ Para cada ITEM en la SECCIÓN:
   ├─ Obtiene: permiso: "productos"
   └─ Pregunta: El usuario tiene acceso?
      ├─ SÍ → Muestra el ITEM
      └─ NO → Omite el ITEM

Resultado:
El usuario ve SOLO el menú que puede usar
```

---

## Integración con Clientes

```
┌────────────────────────────────────────────────────────┐
│           CLIENTES UNIFICADOS (NUEVA PÁGINA)           │
│        /ecommerce/admin/clientes_unificado.php         │
└────────┬──────────────────────────────────────────┬───┘
         │                                          │
    ┌────▼────────────────┐         ┌──────────────▼───┐
    │  CLIENTES WEB       │         │ CLIENTES COTIZACIÓN
    │                     │         │                   │
    │ ecommerce_clientes  │         │ ecommerce_cotiza- │
    │                     │         │ cion_clientes     │
    │ ├─ 45 clientes      │         │                   │
    │ ├─ email verificado │         │ ├─ 12 clientes    │
    │ ├─ google/email     │         │ ├─ nombre         │
    │ └─ con pedidos      │         │ └─ para cotizar   │
    │                     │         │                   │
    │ Editar:             │         │ Editar:           │
    │ clientes_web_      │         │ cotizacion_       │
    │ editar.php          │         │ clientes_         │
    └─────────────────────┘         │ editar.php        │
                                    └───────────────────┘
         │                                  │
         └──────────────────┬───────────────┘
                            │
                    ┌───────▼────────┐
                    │  UNA SOLA TABLA │
                    │  CON TABS PARA  │
                    │   FILTRAR POR   │
                    │      TIPO       │
                    └────────────────┘

Ventajas:
✓ No ir a dos páginas diferentes
✓ Ver estadísticas combinadas
✓ Filtrar por tipo en la misma vista
✓ Editar desde la misma tabla
```

---

## Ciclo de Vida de una Sección

```
1. CREAR SECCIÓN
   └─ Ir a: menu_configuracion.php
   └─ Llenar: seccion, label, icono
   └─ Acción: INSERT INTO ecommerce_menu_configuracion
   └─ Resultado: Nueva sección aparece en listado

2. SECCIÓN ACTIVA
   └─ Aparece en menú del admin
   └─ Se muestra solo si usuario tiene permisos
   └─ Items dentro se pueden gestionar

3. DESACTIVAR SECCIÓN
   └─ Ir a: menu_configuracion.php
   └─ Hacer click: Ícono 👁️
   └─ Acción: UPDATE ... SET activo = 0
   └─ Resultado: Sección se oculta del menú (SIN eliminar)

4. REACTIVAR SECCIÓN
   └─ Ir a: menu_configuracion.php (muestra también inactivas)
   └─ Hacer click: Ícono 👁️ (está gris)
   └─ Acción: UPDATE ... SET activo = 1
   └─ Resultado: Sección reaparece en menú

5. ELIMINAR SECCIÓN
   └─ Ir a: menu_configuracion.php
   └─ Hacer click: Ícono 🗑️
   └─ Acción: DELETE ... (con cascada a items)
   └─ Resultado: Sección e items se eliminan permanentemente
```

---

## Checklist Visual de Implementación

```
Paso 1: SETUP ✓
┌─────────────────────────────────────┐
│ ✓ Ejecutar setup_menu_configuracion │
│ ✓ Crear tablas en BD                │
│ ✓ Insertar datos por defecto        │
│ ✓ Mostrar mensaje de éxito          │
└─────────────────────────────────────┘

Paso 2: ADMINISTRACIÓN ✓
┌─────────────────────────────────────┐
│ ✓ Acceder a menu_configuracion.php  │
│ ✓ Ver 8 secciones por defecto       │
│ ✓ Agregar nueva sección (test)      │
│ ✓ Desactivar una sección            │
│ ✓ Eliminar sección de test          │
└─────────────────────────────────────┘

Paso 3: ITEMS ✓
┌─────────────────────────────────────┐
│ ✓ Acceder a menu_items.php          │
│ ✓ Ver items de una sección          │
│ ✓ Agregar nuevo item                │
│ ✓ Desactivar un item                │
│ ✓ Eliminar item de test             │
└─────────────────────────────────────┘

Paso 4: CLIENTES UNIFICADOS ✓
┌─────────────────────────────────────┐
│ ✓ Acceder a clientes_unificado.php  │
│ ✓ Ver tab "Todos"                   │
│ ✓ Ver tab "Web"                     │
│ ✓ Ver tab "Cotización"              │
│ ✓ Editar cliente desde la tabla     │
└─────────────────────────────────────┘

Paso 5: DOCUMENTACIÓN ✓
┌─────────────────────────────────────┐
│ ✓ Leer README_MODULO_MENU.md        │
│ ✓ Leer GUIA_MENU_CONFIGURACION.md   │
│ ✓ Leer RESUMEN_MODULO_MENU.md       │
│ ✓ Implementación completa!          │
└─────────────────────────────────────┘
```

---

**Diagrama Visual Completado - Julio 2024**
