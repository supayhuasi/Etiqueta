#!/usr/bin/env php
# 🚀 QUICK START - Módulo de Menú del Ecommerce

## 5 MINUTOS PARA IMPLEMENTAR

### ✅ PASO 1: Setup (1 minuto)

Accede a tu navegador:
```
http://tu-sitio.com/ecommerce/setup_menu_configuracion.php
```

Verás esto:
```
✓ Tabla 'ecommerce_menu_configuracion' creada correctamente.
✓ Tabla 'ecommerce_menu_items' creada correctamente.
✓ Configuraciones de menú por defecto insertadas.
✓ Items de menú por defecto insertados.

[Ir a Configuración del Menú] [Volver al Admin]
```

**Si ya existe:** No hay problema, no duplica. ✅

---

### ✅ PASO 2: Ir a Configuración (1 minuto)

Como admin, accede a:
```
/ecommerce/admin/menu_configuracion.php
```

Verás:
```
⚙️ Configuración del Menú del Ecommerce
├─ Dashboard         [👁️] [🗑️]
├─ Catálogo         [👁️] [🗑️]
├─ Empresa          [👁️] [🗑️]
├─ Ventas           [👁️] [🗑️]  ← AQUÍ ESTÁ CLIENTES
├─ Compras          [👁️] [🗑️]
├─ Recursos HH      [👁️] [🗑️]
├─ Finanzas         [👁️] [🗑️]
└─ Sistema          [👁️] [🗑️]
```

---

### ✅ PASO 3: Ver Items (1 minuto)

Haz click en el ✏️ de "Ventas"

Verás:
```
📋 Items de Menú - Ventas

├─ Pedidos                   [👁️] [🗑️]
├─ Órdenes de Producción    [👁️] [🗑️]
├─ Instalaciones            [👁️] [🗑️]
├─ CRM Seguimiento          [👁️] [🗑️]
├─ CLIENTES UNIFICADO ⭐    [👁️] [🗑️]  ← NUEVO!
└─ Cotizaciones             [👁️] [🗑️]
```

Ese item NUEVO lleva a: `/ecommerce/admin/clientes_unificado.php`

---

### ✅ PASO 4: Acceder a Clientes Unificados (1 minuto)

Como admin, accede a:
```
/ecommerce/admin/clientes_unificado.php
```

Verás:
```
👥 Clientes Unificado

[Todos (57)] [Web (45)] [Cotización (12)]

Tabla con clientes:
├─ 45 clientes Web (con email, estado, etc.)
└─ 12 clientes Cotización (nombre, etc.)

Todo en una sola página ✅
```

---

### ✅ PASO 5: Documentarse (1 minuto)

Abre en tu editor:
```
/ecommerce/README_MODULO_MENU.md
```

Contiene TODO lo que necesitas saber.

---

## 🎯 CASOS DE USO BÁSICOS

### Caso 1: Agregar Nueva Sección

```
1. /ecommerce/admin/menu_configuracion.php
2. Llena el formulario:
   Clave:    mi_seccion
   Label:    Mi Sección
   Ícono:    bi bi-gear
   Título:   Descripción
3. Click [AGREGAR]
```

**Resultado:** Nueva sección aparece inmediatamente ✅

---

### Caso 2: Agregar Item a Sección

```
1. /ecommerce/admin/menu_configuracion.php
2. Click en ✏️ de una sección → menu_items.php
3. Llena el formulario:
   Título:   Mi Item
   URL:      /ecommerce/admin/mi_pagina.php
   Ícono:    bi bi-box
   Permiso:  mi_permiso (opcional)
4. Click [AGREGAR]
```

**Resultado:** Item aparece en la sección ✅

---

### Caso 3: Desactivar Sin Eliminar

```
1. /ecommerce/admin/menu_configuracion.php
2. Click en ícono 👁️ de una sección
```

**Resultado:** Sección se oculta del menú (pero sigue en BD) ✅

---

### Caso 4: Ver Clientes Mezclados

```
1. /ecommerce/admin/clientes_unificado.php
2. Tab: "Todos"
```

**Resultado:** 57 clientes en una tabla (45 web + 12 cotización) ✅

---

### Caso 5: Ver Solo Clientes Web

```
1. /ecommerce/admin/clientes_unificado.php
2. Tab: "Web"
```

**Resultado:** Solo 45 clientes web con todos sus datos ✅

---

## 📚 DOCUMENTACIÓN POR CASO

| Quiero... | Documento | Línea aprox. |
|-----------|-----------|------------|
| Empezar rápido | README_MODULO_MENU.md | Intro |
| Entender todo | GUIA_MENU_CONFIGURACION.md | Completa |
| Ver cómo funciona | DIAGRAMA_VISUAL_MENU.md | Inicio |
| Entender beneficios | COMPARATIVA_ANTES_DESPUES.md | Intro |
| Lista de archivos | INDICE_ARCHIVOS.md | Intro |
| Arquitectura técnica | RESUMEN_MODULO_MENU.md | Inicio |

---

## 🔐 SEGURIDAD

Todos los formularios:
- ✅ Usan CSRF Token
- ✅ Prepared Statements
- ✅ Validan datos
- ✅ Solo admin accede

---

## ⚠️ PROBLEMAS COMUNES

### S: El setup no funciona
**R:** Verificar que `/ecommerce/config.php` existe y tiene BD conectada

### S: No veo las opciones nuevas
**R:** Hacer refresh (Ctrl+F5)

### S: Quiero volver atrás
**R:** Eliminar tablas `ecommerce_menu_*` de BD

### S: Solo admin puede acceder?
**R:** Sí, por seguridad. Solo admin configura menú

---

## 🎓 ATAJOS ÚTILES

```bash
# URLs rápidas (copiar y pegar)

# Setup
http://tu-sitio/ecommerce/setup_menu_configuracion.php

# Menú
http://tu-sitio/ecommerce/admin/menu_configuracion.php

# Items
http://tu-sitio/ecommerce/admin/menu_items.php

# Clientes
http://tu-sitio/ecommerce/admin/clientes_unificado.php
```

---

## 📊 RESUMEN EN 1 LÍNEA

**Se creó un panel para configurar el menú del admin SIN editar código, y se unificó el acceso a clientes en una sola página.**

---

## ✨ LO QUE SIGUE

1. ✅ Ejecutar setup
2. ✅ Acceder a configuración
3. ✅ Personalizar menú
4. ✅ Usar clientes unificados
5. ✅ Documentar cambios
6. ✅ Entrenar equipo
7. 🔄 Expansiones futuras

---

# 📞 PREGUNTAS FRECUENTES

**P: ¿Puedo editar items existentes?**  
R: Actualmente no, pero puedes desactivar y crear uno nuevo.

**P: ¿Los cambios son inmediatos?**  
R: Sí, se guardan en BD al instante.

**P: ¿Puedo eliminar todo y volver atrás?**  
R: Sí, eliminar las tablas y el sistema usa menú antiguo.

**P: ¿Funciona con todos los roles?**  
R: Sí, respeta los permisos configurados en header.php

**P: ¿Es seguro?**  
R: Totalmente, usa CSRF, prepared statements, sanitización.

---

# 🚀 COMIENZA AHORA

## El camino más rápido es:

1. Abre en navegador:
   ```
   http://tu-sitio/ecommerce/setup_menu_configuracion.php
   ```

2. Verás confirmación de éxito ✅

3. Haz click en:
   ```
   [Ir a Configuración del Menú]
   ```

4. ¡Ya estás adentro! 🎉

---

*Listo? Go to `/ecommerce/setup_menu_configuracion.php` now!*

---

*Quick Start - Julio 2024*
