# ⚡ COMENZAR AQUÍ - Módulo de Menú del Ecommerce

## 🎯 Qué se ha creado

Se ha implementado un **módulo completo de configuración del menú del ecommerce** que permite:

- ✅ Agregar/eliminar opciones del menú SIN editar código
- ✅ Activar/desactivar secciones sin eliminarlas
- ✅ Gestionar permisos de acceso
- ✅ **UNIFICAR** acceso a Clientes Web + Clientes Cotización en una sola página
- ✅ Interfaz gráfica fácil de usar

---

## 🚀 INSTALACIÓN (3 PASOS)

### 1️⃣ Ejecutar Setup
Accede a esta URL en tu navegador:
```
http://tu-sitio/ecommerce/setup_menu_configuracion.php
```

Esto creará automáticamente:
- Tablas en la BD: `ecommerce_menu_configuracion` y `ecommerce_menu_items`
- 8 secciones por defecto
- ~30 items por defecto

✅ **El setup es SEGURO** - Si ya existen tablas, no las borra

---

### 2️⃣ Acceder a Configuración
Accede como **administrador** a:

#### Gestionar Secciones:
```
/ecommerce/admin/menu_configuracion.php
```
Aquí puedes:
- Ver todas las secciones
- Agregar nueva sección
- Activar/desactivar sección
- Eliminar sección

#### Gestionar Items:
```
/ecommerce/admin/menu_items.php?seccion_id=ID
```
(O hacer click en el botón ✏️ desde menu_configuracion.php)

Aquí puedes:
- Ver todos los items de una sección
- Agregar nuevo item
- Activar/desactivar item
- Eliminar item

---

### 3️⃣ Usar Clientes Unificados
```
/ecommerce/admin/clientes_unificado.php
```

**ANTES:** Ibas a dos lugares diferentes:
- Clientes Web: `/ecommerce/admin/clientes_web.php`
- Clientes Cotización: `/ecommerce/admin/cotizacion_clientes.php`

**AHORA:** Una sola página con tabs:
- Todos
- Solo Web
- Solo Cotización

---

## 📁 Archivos Creados

```
ecommerce/
├── setup_menu_configuracion.php           ← Ejecutar UNA SOLA VEZ
├── GUIA_MENU_CONFIGURACION.md             ← Documentación completa
├── RESUMEN_MODULO_MENU.md                 ← Arquitectura técnica
│
└── admin/
    ├── menu_configuracion.php             ← Gestionar secciones
    ├── menu_items.php                     ← Gestionar items
    ├── clientes_unificado.php             ← NUEVA página unificada
    │
    └── includes/
        └── menu_helper.php                ← Helper interno (para futuro)
```

---

## ✨ Características Principales

### Agregar Nueva Sección
1. Ir a: `/ecommerce/admin/menu_configuracion.php`
2. Llenar formulario:
   - **Clave:** `mi_seccion` (único, sin espacios)
   - **Etiqueta:** `Mi Sección` (lo que se ve)
   - **Ícono:** `bi bi-gear` (Bootstrap Icons)
   - **Título:** `Descripción de la sección`
3. Hacer click en "Agregar"

### Agregar Item a Sección
1. Ir a: `/ecommerce/admin/menu_items.php?seccion_id=3`
2. Llenar formulario:
   - **Título:** `Gestionar Productos`
   - **URL:** `/ecommerce/admin/productos.php`
   - **Ícono:** `bi bi-box`
   - **Permiso:** `productos` (opcional)
3. Hacer click en "Agregar"

### Ver Clientes Unificados
1. Ir a: `/ecommerce/admin/clientes_unificado.php`
2. Usar tabs para filtrar:
   - **Todos:** Web + Cotización
   - **Web:** Solo clientes del ecommerce
   - **Cotización:** Solo clientes de cotización
3. Editar o ver detalles desde la misma tabla

---

## 🔗 Ícones Disponibles

El módulo usa **Bootstrap Icons**. Algunos ejemplos:

| Ícono | Código |
|-------|--------|
| 🏠 Casa | `bi bi-house-door` |
| 📦 Caja | `bi bi-box` |
| 🛒 Carrito | `bi bi-cart-check` |
| 👥 Personas | `bi bi-people` |
| ⚙️ Engranaje | `bi bi-gear-fill` |
| 💰 Dinero | `bi bi-cash-stack` |
| 📊 Gráfico | `bi bi-bar-chart` |

**Ver todos:** https://icons.getbootstrap.com/

---

## ⚠️ Importante

- **Solo administradores** pueden acceder a la configuración
- Los cambios se guardan **inmediatamente** en BD
- Si desactivas una sección, **no desaparece** (solo se oculta del menú)
- Si eliminas una sección, se **eliminan TODOS sus items** también
- El sistema es **seguro**: usa CSRF tokens y prepared statements

---

## 🆘 Si algo va mal

### El setup no funciona
→ Ir a `/ecommerce/config.php` y verificar conexión a BD

### No veo opciones de menú
→ Ejecutar el setup: `/ecommerce/setup_menu_configuracion.php`

### Deseo volver al menú antiguo
→ Eliminar las tablas `ecommerce_menu_configuracion` y `ecommerce_menu_items`
→ El sistema detectará que no existen y usará el menú hardcodeado

---

## 📚 Documentación

**Este archivo:** `README_MODULO_MENU.md` (visión rápida)

**Guía completa:** `GUIA_MENU_CONFIGURACION.md` (detallado)

**Arquitectura técnica:** `RESUMEN_MODULO_MENU.md` (para developers)

---

## 🎓 Ejemplos Prácticos

### Ejemplo 1: Crear sección "Reportes"
```
Clave:    reportes
Label:    Reportes
Ícono:    bi bi-file-earmark-text
Título:   Acceder a reportes del sistema

Items a agregar:
├─ Reportes de Ventas     [/ecommerce/admin/ventas_reportes.php]
├─ Estadísticas Productos [/ecommerce/admin/estadisticas_productos.php]
└─ KPIs Dinámicos         [/ecommerce/admin/kpis.php]
```

### Ejemplo 2: Ocultar sección temporalmente
```
1. Ir a: /ecommerce/admin/menu_configuracion.php
2. Hacer click en el ícono 👁️ de la sección
3. La sección se desactiva (sin ser eliminada)
4. Volver a hacer click para activarla
```

### Ejemplo 3: Ver todos los clientes unificados
```
1. Ir a: /ecommerce/admin/clientes_unificado.php
2. Ver tab "Todos" con 45 clientes web + 12 de cotización
3. Filtrar por tipo si necesitas solo uno
4. Editar cliente desde la misma página
```

---

## ✅ Checklist de Implementación

- [ ] Ejecutar `/ecommerce/setup_menu_configuracion.php`
- [ ] Verificar que se crearon las tablas (sin errores)
- [ ] Acceder a `/ecommerce/admin/menu_configuracion.php` como admin
- [ ] Verificar que aparecen las 8 secciones por defecto
- [ ] Hacer click en una sección para ver sus items
- [ ] Ir a `/ecommerce/admin/clientes_unificado.php`
- [ ] Ver que aparecen clientes web y de cotización
- [ ] Probar tabs de filtro (Todos, Web, Cotización)
- [ ] Leer `GUIA_MENU_CONFIGURACION.md` para más detalles
- [ ] ¡Listo! El módulo está operativo

---

## 🚀 Próximos Pasos Opcionales

### Personalizar menú según tu negocio:
1. Agregar nuevas secciones si necesitas
2. Quitar items que no uses
3. Reordenar por importancia
4. Ajustar ícones y etiquetas

### Integración con roles:
1. En `header.php` ajustar `$role_permissions` si tienes roles custom
2. El menú dinámico respetará esos permisos automáticamente

### Monitoreo:
1. Documentar tu configuración personalizada
2. Hacer backups de las tablas `ecommerce_menu_*`

---

**Listo para empezar? 👉 Ve a: `/ecommerce/setup_menu_configuracion.php`**

---

*Módulo implementado - Julio 2024*
*Para preguntas detalladas, ver: GUIA_MENU_CONFIGURACION.md*
