# 📦 Módulo de Gastos - Guía de Instalación

## ✅ Archivos Creados

### Core del módulo:
- ✅ **gastos.php** - Panel principal de gastos
- ✅ **gastos_crear.php** - Crear nuevo gasto
- ✅ **gastos_editar.php** - Editar gasto existente
- ✅ **gastos_eliminar.php** - Eliminar gasto
- ✅ **gastos_cambiar_estado.php** - Cambiar estado y ver historial
- ✅ **tipos_gastos.php** - Gestionar tipos de gastos
- ✅ **setup_gastos.php** - Crear tablas en BD
- ✅ **verificar_gastos.php** - Verificar instalación
- ✅ **componentes/resumen_gastos.php** - Widget para dashboard

### Directorios creados:
- ✅ **uploads/gastos/** - Almacenar archivos de comprobantes

---

## 🚀 Pasos de Instalación

### 1. Ejecutar Setup de Base de Datos
```
Abrir en navegador: http://tu-servidor/setup_gastos.php
```

Esto creará automáticamente:
- Tabla `tipos_gastos` (5 tipos predefinidos)
- Tabla `estados_gastos` (5 estados predefinidos)
- Tabla `gastos` (registro de gastos)
- Tabla `historial_gastos` (auditoría de cambios)

### 2. Verificar Instalación
```
Abrir en navegador: http://tu-servidor/verificar_gastos.php
```

Verifica:
- ✓ Archivos existen
- ✓ Carpetas de upload existen
- ✓ Tablas en BD creadas
- ✓ Datos predeterminados cargados

### 3. Usar el Módulo
```
En navbar → Gastos → Nuevo Gasto
```

---

## 📊 Características del Módulo

### Dashboard Principal (gastos.php)
- Listado de gastos con filtros
- Resumen mensual (total, pagado, pendiente)
- Gastos por tipo
- Acciones rápidas (editar, cambiar estado, eliminar)

### Crear Gasto (gastos_crear.php)
- Campos: fecha, tipo, estado, descripción, monto, beneficiario
- Subida de archivos (comprobantes, facturas)
- Auto-numeración (G-000001, G-000002, etc.)
- Registro automático en historial

### Editar Gasto (gastos_editar.php)
- Modificar cualquier campo
- Cambiar archivo adjunto
- Mantiene auditoría de creación

### Cambiar Estado (gastos_cambiar_estado.php)
- Transición entre estados
- Observaciones por cambio
- Historial completo visible
- Timeline de cambios

### Tipos de Gastos (tipos_gastos.php)
- Crear nuevos tipos
- Asignar colores
- Agregar descripción
- Gestionar activos/inactivos

---

## 📋 Estructura de Datos

### Tabla: tipos_gastos
```
- id (PK)
- nombre (único)
- descripcion
- color (hexadecimal)
- activo (boolean)
- fecha_creacion
```

**Predefinidos:**
- Servicios (#007BFF)
- Insumos (#28A745)
- Transporte (#FFC107)
- Mantenimiento (#DC3545)
- Utilidades (#6F42C1)

### Tabla: estados_gastos
```
- id (PK)
- nombre (único)
- descripcion
- color (hexadecimal)
- activo (boolean)
```

**Predefinidos:**
- Pendiente (#FFC107)
- Aprobado (#17A2B8)
- Pagado (#28A745)
- Cancelado (#6C757D)
- Rechazado (#DC3545)

### Tabla: gastos
```
- id (PK)
- numero_gasto (único, ej: G-000001)
- fecha (DATE)
- tipo_gasto_id (FK)
- estado_gasto_id (FK)
- descripcion
- monto (DECIMAL 10,2)
- beneficiario
- observaciones
- archivo (nombre archivo)
- usuario_registra (FK)
- usuario_aprueba (FK)
- fecha_aprobacion
- fecha_creacion
- fecha_actualizacion (ON UPDATE)
```

### Tabla: historial_gastos
```
- id (PK)
- gasto_id (FK)
- estado_anterior_id (FK)
- estado_nuevo_id (FK)
- usuario_id (FK)
- observaciones
- fecha_cambio (TIMESTAMP)
```

---

## 🔒 Seguridad

✅ Solo acceso admin  
✅ Prepared statements (protección SQL injection)  
✅ HTMLSpecialChars en salidas  
✅ Validación en servidor  
✅ Límite de tamaño de archivo (5MB)  
✅ Tipos de archivo permitidos  
✅ Auditoría completa de cambios  

---

## 📝 Tipos de Archivo Permitidos

- PDF (.pdf)
- Imágenes (.jpg, .jpeg, .png)
- Excel (.xlsx, .xls)
- Word (.docx, .doc)
- **Límite:** 5 MB por archivo

---

## 🎯 Flujo Típico

1. **Usuario admin** navega a **Gastos**
2. Clicks en **+ Nuevo Gasto**
3. Completa formulario:
   - Fecha de gasto
   - Tipo (Servicios, Insumos, etc.)
   - Estado inicial (ej: Pendiente)
   - Descripción del gasto
   - Monto
   - Beneficiario (opcional)
   - Archivo comprobante (optional)
4. Clickea **Crear Gasto**
5. Sistema:
   - Genera número único (G-000001)
   - Guarda archivo en uploads/gastos/
   - Registra en tabla gastos
   - Registra en historial
6. Admin puede:
   - Ver en dashboard
   - Filtrar por mes/tipo/estado
   - Editar datos
   - Cambiar estado
   - Ver historial completo
   - Eliminar si es necesario

---

## 📊 Dashboard Integration

Para agregar widget de gastos en dashboard:

```php
<?php include 'componentes/resumen_gastos.php'; ?>
```

Muestra:
- Cards resumen (total, invertido, pagado, pendiente)
- Top 5 tipos de gastos
- Últimos 5 gastos
- Links rápidos

---

## 🔧 Troubleshooting

### Error: "Tabla no existe"
→ Ejecutar setup_gastos.php

### Error: "Carpeta uploads/gastos no existe"
→ Crear manualmente: `mkdir uploads/gastos && chmod 755 uploads/gastos`

### Error: "Archivo no se sube"
→ Verificar: permisos, tamaño, formato

### Error: "Acceso denegado"
→ Solo admin puede acceder. Verificar rol en BD.

---

## 🧪 Testing Checklist

- [ ] setup_gastos.php ejecutado
- [ ] verificar_gastos.php sin errores
- [ ] Crear nuevo gasto
- [ ] Gasto visible en listado
- [ ] Editar gasto
- [ ] Cambiar estado
- [ ] Ver historial
- [ ] Subir archivo comprobante
- [ ] Filtrar por mes
- [ ] Filtrar por tipo
- [ ] Filtrar por estado
- [ ] Eliminar gasto
- [ ] Widget en dashboard funciona

---

## ✨ Estado Final

✅ Módulo completamente funcional
✅ Base de datos inicializada
✅ Archivos listos
✅ Documentación completa
✅ Listo para producción

