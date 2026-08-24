# 🚗 Módulo de Seguros y Permisos - Guía de Instalación

Control de vigencia de seguros y permisos por vehículo, con alertas de vencimiento en el dashboard.

## ✅ Archivos del módulo

- **seguros.php** - Listado principal con filtros y resumen de vigencias
- **seguros_crear.php** - Crear nuevo registro (incluye botón "Renovar" que precarga los datos de uno existente)
- **seguros_editar.php** - Editar registro existente
- **seguros_eliminar.php** - Eliminar registro
- **seguros_archivo.php** - Servir el archivo adjunto (póliza/permiso escaneado)
- **tipos_seguros.php** - Gestionar tipos (Seguro, VTV/RTO, Permiso de circulación, etc.)
- **setup_seguros.php** - Crear tablas en la base de datos

### Directorio creado
- **uploads/** (dentro de `ecommerce/admin/seguros/`) - Almacena los adjuntos (se crea solo en el primer upload)

---

## 🚀 Pasos de instalación

### 1. Ejecutar setup de base de datos
```
Abrir en el navegador: https://tu-servidor/ecommerce/admin/seguros/setup_seguros.php
```
Esto crea:
- Tabla `tipos_seguros_permisos` (con 5 tipos predefinidos: Seguro, VTV/RTO, Permiso de circulación, Cédula verde/azul, Otro)
- Tabla `seguros_permisos` (los registros por vehículo)

### 2. Acceder al módulo
```
En el navbar → Finanzas → Seguros y Permisos
```
(el menú ya está integrado en `ecommerce/admin/includes/header.php`, no requiere pasos adicionales)

### 3. Permisos por rol
El módulo usa la clave de permiso `seguros`, agregada a los mismos roles que ya tienen acceso a `gastos` (`admin`, `usuario`, `sin_sueldos`). Si otro rol necesita acceso, agregar `'seguros'` a su lista en `$role_permissions` dentro de `header.php`.

---

## 📊 Qué controla

### Dashboard principal (seguros.php)
- Tarjetas resumen: Total registrados, Vigentes, Por vencer, Vencidos
- Filtros por patente, tipo y estado de vigencia
- El umbral de "por vencer" es configurable desde la propia pantalla (por defecto 30 días)

### Crear / Editar registro
Campos: patente del vehículo, descripción del vehículo, tipo, número de póliza/permiso, compañía/organismo emisor, fecha de emisión, **fecha de vencimiento** (obligatoria), costo, observaciones, archivo adjunto (máx. 5MB: PDF, imágenes, Excel, Word).

### Renovación
No hay un botón de "renovar in-place": cada renovación se guarda como un **registro nuevo** (vía el botón 🔄 en el listado, que precarga patente/tipo/número/entidad del registro elegido). Así queda el historial completo de pólizas/permisos anteriores por vehículo.

### Alertas de vencimiento
- En el dashboard del admin (campana de notificaciones), igual que "Gastos por vencer": aparecen los seguros/permisos que vencen dentro de los próximos 30 días o que ya vencieron, solo visibles para admin.
- Cada usuario puede marcar las notificaciones como leídas (se ocultan hasta que aparezca un vencimiento nuevo), igual que el resto de notificaciones persistentes del panel.

---

## 📋 Estructura de datos

### Tabla: tipos_seguros_permisos
```
- id (PK)
- nombre (único)
- descripcion
- color (hexadecimal)
- activo (boolean)
- fecha_creacion
```

### Tabla: seguros_permisos
```
- id (PK)
- vehiculo_patente
- vehiculo_descripcion
- tipo_id (FK)
- numero (número de póliza o permiso)
- entidad (compañía de seguros u organismo emisor)
- fecha_emision
- fecha_vencimiento
- costo
- archivo
- observaciones
- usuario_registra (FK)
- fecha_creacion
- fecha_actualizacion
```

---

## 🔒 Seguridad

✅ Solo usuarios con el permiso `seguros` (admin/usuario/sin_sueldos por defecto)
✅ Prepared statements (protección SQL injection)
✅ HTMLSpecialChars en salidas
✅ Límite de tamaño de archivo (5MB) y tipos de archivo permitidos
✅ El adjunto se sirve por `seguros_archivo.php` verificando sesión y rol, nunca por URL directa a `uploads/`

---

## 🔧 Troubleshooting

### Error: "Tabla no existe"
→ Ejecutar `setup_seguros.php`

### No aparece "Seguros y Permisos" en el menú
→ Verificar que el usuario tenga el permiso `seguros` en `$role_permissions` (header.php) y refrescar sesión (logout/login)

### El adjunto no se sube
→ Verificar permisos de escritura en `ecommerce/admin/seguros/uploads/` (se crea automáticamente, pero el proceso PHP necesita permiso de escritura en `ecommerce/admin/seguros/`)
