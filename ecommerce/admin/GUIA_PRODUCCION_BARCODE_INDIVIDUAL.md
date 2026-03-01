# 🏷️ Sistema de Códigos de Barras Individuales para Órdenes de Producción

## 📋 Descripción General

Sistema avanzado de trazabilidad que asigna un código de barras único a **cada producto individual** dentro de una orden de producción, permitiendo un control granular y seguimiento en tiempo real del proceso productivo.

---

## 🎯 Mejora Implementada

### ❌ Sistema Anterior
- **Un código por orden completa**
- Seguimiento general sin detalle
- Difícil identificar qué productos específicos están listos
- No se sabe quién trabajó en cada pieza

### ✅ Sistema Nuevo
- **Un código por cada producto individual**
- Seguimiento pieza por pieza
- Control detallado del progreso
- Trazabilidad completa (quién, cuándo, qué)
- Mayor eficiencia y control de calidad

---

## 📦 Componentes del Sistema

### 1. Base de Datos
**Tabla:** `ecommerce_produccion_items_barcode`

```sql
CREATE TABLE ecommerce_produccion_items_barcode (
    id INT PRIMARY KEY AUTO_INCREMENT,
    orden_produccion_id INT NOT NULL,
    pedido_item_id INT NOT NULL,
    numero_item INT NOT NULL,              -- Ej: 1 de 5, 2 de 5
    codigo_barcode VARCHAR(50) UNIQUE,     -- OP000001-IT000001-001
    estado ENUM(...),
    usuario_inicio INT,
    fecha_inicio DATETIME,
    usuario_termino INT,
    fecha_termino DATETIME,
    observaciones TEXT
)
```

### 2. Generador de Etiquetas
**Archivo:** `orden_produccion_etiquetas_pdf.php`

Genera PDF con todas las etiquetas individuales:
- Una etiqueta por cada producto
- Código de barras Code128
- Información del producto
- Número secuencial
- Referencia a la orden

### 3. Interfaz de Escaneo
**Archivo:** `orden_produccion_escaneo.php`

Interfaz para operarios de producción:
- Escaneo de código de barras
- Cambio de estado en tiempo real
- Estadísticas del día
- Lista de items activos

### 4. API de Procesamiento
**Archivo:** `orden_produccion_escaneo_api.php`

Backend que procesa:
- Búsqueda de items
- Inicio de producción
- Finalización de items
- Rechazo de piezas defectuosas

---

## 🔄 Flujo del Proceso

```
┌─────────────────────────────────────────────────┐
│  PASO 1: CREAR ORDEN DE PRODUCCIÓN             │
├─────────────────────────────────────────────────┤
│  • Se crea orden desde el pedido                │
│  • Estado inicial: pendiente                    │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│  PASO 2: GENERAR ETIQUETAS INDIVIDUALES        │
├─────────────────────────────────────────────────┤
│  • Acceder a "Generar Etiquetas Individuales"  │
│  • Sistema crea código único para cada item     │
│  • Se genera PDF con todas las etiquetas       │
│  • Ejemplo: Pedido de 5 cortinas = 5 códigos   │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│  PASO 3: IMPRIMIR Y PEGAR ETIQUETAS            │
├─────────────────────────────────────────────────┤
│  • Descargar PDF generado                       │
│  • Imprimir etiquetas                           │
│  • Pegar en cada producto/material              │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│  PASO 4: PRODUCCIÓN - INICIAR ITEM             │
├─────────────────────────────────────────────────┤
│  • Operario escanea etiqueta                    │
│  • Sistema muestra info del producto            │
│  • Operario presiona "Iniciar Producción"      │
│  • Estado: pendiente → en_proceso               │
│  • Se registra: quién y cuándo inició          │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│  PASO 5: PRODUCCIÓN - TERMINAR ITEM            │
├─────────────────────────────────────────────────┤
│  • Operario finaliza el producto                │
│  • Escanea nuevamente la etiqueta               │
│  • Presiona "Marcar como Terminado"            │
│  • Estado: en_proceso → terminado               │
│  • Se registra: quién y cuándo finalizó        │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│  PASO 6: CONTROL AUTOMÁTICO                    │
├─────────────────────────────────────────────────┤
│  • Sistema verifica si todos los items están   │
│    terminados                                   │
│  • Si todos completados:                        │
│    → Orden automáticamente a "terminado"       │
│  • Notificación de orden completa               │
└─────────────────────────────────────────────────┘
```

---

## 🏷️ Formato de Códigos de Barras

### Estructura del Código

```
OP000001-IT000001-001
│      │ │      │ │
│      │ │      │ └─ Número secuencial (001, 002, 003...)
│      │ │      └─── ID del item del pedido
│      │ └────────── Prefijo "IT" (Item)
│      └──────────── ID de la orden de producción
└─────────────────── Prefijo "OP" (Orden Producción)
```

### Ejemplos Reales

```
Orden #1, Producto A, 3 unidades:
├─ OP000001-IT000001-001
├─ OP000001-IT000001-002
└─ OP000001-IT000001-003

Orden #1, Producto B, 2 unidades:
├─ OP000001-IT000002-001
└─ OP000001-IT000002-002

Total: 5 etiquetas individuales para esta orden
```

---

## 🎨 Diseño de Etiquetas

```
┌──────────────────────────────────────┐
│  Cortina Blackout Premium            │  ← Nombre del producto
│  Item 1                               │  ← Número secuencial
│                                       │
│  ▐█▌▐▌█▐█▌▐█▌▐▌█▐█▌▐█▌               │  ← Código de barras
│  OP000001-IT000001-001                │  ← Texto del código
│  Orden: P-2026-00123                  │  ← Referencia
└──────────────────────────────────────┘
```

**Especificaciones:**
- Tamaño: 90mm x 40mm
- 2 columnas por página A4
- 12 etiquetas por página (6 filas x 2 columnas)
- Código de barras: Code128

---

## 📱 Interfaz de Escaneo - Características

### Vista Principal

```
┌─────────────────────────────────────────────────┐
│  🏭 Control de Producción                       │
│  Escanee el código de barras del producto       │
├─────────────────────────────────────────────────┤
│                                                 │
│  ┌───────┐  ┌────────┐  ┌──────────┐          │
│  │ 15    │  │ 8      │  │ 45       │          │
│  │ Pend. │  │ En Proc│  │ Termin.  │          │
│  └───────┘  └────────┘  └──────────┘          │
│                                                 │
│  ┌─────────────────────────────────────────┐   │
│  │   🔍 Escanee código aquí...            │   │
│  └─────────────────────────────────────────┘   │
│                                                 │
│  ✅ Item iniciado correctamente                 │
│  Cortina Blackout Premium                       │
│  Item: 1 | Orden: P-2026-00123                 │
│                                                 │
│  [▶️ Iniciar] [✅ Terminar] [❌ Rechazar]       │
├─────────────────────────────────────────────────┤
│  Items Activos en Producción:                   │
│  • Cortina Blackout (Item 1)  [EN PROCESO]     │
│  • Persiana Enrollable (Item 2) [PENDIENTE]    │
└─────────────────────────────────────────────────┘
```

### Acciones Disponibles

1. **Iniciar Producción**
   - Disponible para items en estado "pendiente"
   - Cambia a "en_proceso"
   - Registra operario y hora de inicio

2. **Marcar como Terminado**
   - Disponible para items "en_proceso"
   - Cambia a "terminado"
   - Registra operario y hora de finalización
   - Auto-completa orden si todos los items están listos

3. **Rechazar**
   - Disponible para items "en_proceso"
   - Solicita motivo del rechazo
   - Cambia a "rechazado"
   - Permite reproceso

---

## 📊 Estados del Sistema

### Estados de Items Individuales

```
┌─────────────┬────────────────────────────────────┐
│ Estado      │ Descripción                        │
├─────────────┼────────────────────────────────────┤
│ pendiente   │ Etiqueta generada, aún no iniciado │
│ en_proceso  │ Operario trabajando en el item     │
│ terminado   │ Item completado y aprobado         │
│ rechazado   │ Item defectuoso o con problemas    │
│ entregado   │ Item entregado al cliente          │
└─────────────┴────────────────────────────────────┘
```

### Estados de Orden Completa

```
┌──────────────┬────────────────────────────────────┐
│ Estado       │ Cuándo se alcanza                  │
├──────────────┼────────────────────────────────────┤
│ pendiente    │ Orden creada                       │
│ en_produccion│ Al menos 1 item en proceso         │
│ terminado    │ TODOS los items terminados         │
│ entregado    │ Cliente recibió productos          │
│ cancelado    │ Orden cancelada manualmente        │
└──────────────┴────────────────────────────────────┘
```

---

## 🎯 Ventajas del Sistema

### Para Gerencia

✅ **Visibilidad total** del proceso productivo
✅ **Métricas precisas** de productividad por operario
✅ **Identificación rápida** de cuellos de botella
✅ **Reportes detallados** de tiempos de producción
✅ **Control de calidad** pieza por pieza

### Para Operarios

✅ **Claridad** sobre qué producir
✅ **Sin confusión** entre productos similares
✅ **Seguimiento** de su propio progreso
✅ **Interfaz simple** de usar
✅ **Feedback inmediato** al escanear

### Para Control de Calidad

✅ **Trazabilidad completa**: quién hizo qué
✅ **Registro de rechazos** con motivos
✅ **Identificación** de problemas recurrentes
✅ **Auditoría** completa del proceso

---

## 📈 Reportes y Métricas

### Información Disponible

```sql
-- Productividad por operario (items terminados)
SELECT u.nombre, COUNT(*) as items_terminados
FROM ecommerce_produccion_items_barcode pib
JOIN usuarios u ON pib.usuario_termino = u.id
WHERE pib.estado = 'terminado'
AND DATE(pib.fecha_termino) = CURDATE()
GROUP BY u.id;

-- Tiempo promedio de producción
SELECT 
    AVG(TIMESTAMPDIFF(MINUTE, fecha_inicio, fecha_termino)) as minutos_promedio
FROM ecommerce_produccion_items_barcode
WHERE estado = 'terminado'
AND fecha_termino >= CURDATE();

-- Items rechazados con motivos
SELECT 
    pr.nombre as producto,
    pib.observaciones as motivo_rechazo,
    pib.fecha_termino,
    u.nombre as operario
FROM ecommerce_produccion_items_barcode pib
JOIN ecommerce_pedido_items pi ON pib.pedido_item_id = pi.id
JOIN ecommerce_productos pr ON pi.producto_id = pr.id
JOIN usuarios u ON pib.usuario_termino = u.id
WHERE pib.estado = 'rechazado'
ORDER BY pib.fecha_termino DESC;
```

---

## 🛠️ Configuración e Instalación

### Paso 1: Ejecutar Setup

```
1. Acceder a: /ecommerce/setup_produccion_barcode.php
2. El setup creará:
   - Tabla ecommerce_produccion_items_barcode
   - Columna items_generados en ordenes
3. Verificar mensaje de éxito
```

### Paso 2: Hardware Necesario

```
Estación de Producción:
├─ 💻 Tablet o PC
├─ 📷 Lector de código de barras USB
├─ 🖨️ Impresora (para etiquetas)
└─ 📶 Conexión a red/internet
```

### Paso 3: Configurar Lector

```
Configuración del lector:
✓ Tipo: Code128 habilitado
✓ Sufijo: Enter (nueva línea)
✓ Modo: Keyboard wedge
✓ Velocidad: Normal/Rápida
```

---

## 📋 Guía de Uso Rápido

### Para Administrador

1. **Crear orden de producción** desde el pedido
2. **Acceder al detalle** de la orden
3. **Click en "Generar Etiquetas Individuales"**
4. **Descargar e imprimir** el PDF
5. **Pegar etiquetas** en productos/materiales

### Para Operario de Producción

1. **Abrir interfaz** de escaneo de producción
2. **Tomar producto** con etiqueta
3. **Escanear código** de barras
4. **Presionar "Iniciar Producción"**
5. *Trabajar en el producto*
6. **Escanear nuevamente** al terminar
7. **Presionar "Marcar como Terminado"**
8. **Repetir** con siguiente producto

---

## 🎪 Escenarios de Uso

### Escenario 1: Producción Simple

```
Pedido: 3 cortinas iguales

Flujo:
1. Se generan 3 etiquetas individuales
2. Operario A escanea etiqueta #1 → Inicia
3. Operario A termina → Escanea → Termina
4. Operario A escanea etiqueta #2 → Inicia
5. Operario A termina → Escanea → Termina
6. Operario A escanea etiqueta #3 → Inicia
7. Operario A termina → Escanea → Termina
8. Sistema: Orden completa automáticamente
```

### Escenario 2: Producción Paralela

```
Pedido: 10 productos

Flujo:
1. Se generan 10 etiquetas
2. Operario A toma etiquetas #1-5
3. Operario B toma etiquetas #6-10
4. Trabajan simultáneamente
5. Cada uno escanea al iniciar y terminar
6. Sistema trackea progreso individual
7. Cuando todos terminan → Orden completa
```

### Escenario 3: Control de Calidad

```
Defecto encontrado:

Flujo:
1. Operario escanea producto terminado
2. Inspector ve defecto
3. Operario escanea de nuevo
4. Selecciona "Rechazar"
5. Ingresa motivo: "Costura defectuosa"
6. Item marcado como rechazado
7. Se genera nuevo item para rehacer
```

---

## 🔒 Seguridad y Auditoría

### Información Registrada

```
Para cada item:
✓ Quién inició la producción
✓ Hora exacta de inicio
✓ Quién finalizó la producción
✓ Hora exacta de finalización
✓ Tiempo total de producción
✓ Observaciones (si hay)
✓ Estado final (terminado/rechazado)
```

### Trazabilidad

- **Completa**: Desde inicio hasta entrega
- **Inmutable**: No se pueden modificar registros históricos
- **Auditab le**: Todos los cambios quedan registrados
- **Transparente**: Visible para gerencia

---

## 📁 Archivos del Sistema

```
ecommerce/
├── setup_produccion_barcode.php
│   └─ Setup inicial de base de datos
│
└── admin/
    ├── orden_produccion_detalle.php (modificado)
    │   └─ Botones para generar etiquetas
    │
    ├── orden_produccion_etiquetas_pdf.php
    │   └─ Generador de etiquetas PDF
    │
    ├── orden_produccion_escaneo.php
    │   └─ Interfaz principal de escaneo
    │
    ├── orden_produccion_escaneo_api.php
    │   └─ API para procesar escaneos
    │
    └── ordenes_produccion.php (modificado)
        └─ Acceso rápido a control de producción
```

---

## 🚀 Próximos Pasos

### Mejoras Sugeridas

- [ ] **Dashboard de producción** con métricas en vivo
- [ ] **Notificaciones push** cuando orden completa
- [ ] **App móvil** para operarios
- [ ] **Reportes automáticos** diarios/semanales
- [ ] **Alertas** de items atrasados
- [ ] **Integración con cámaras** para fotos de piezas
- [ ] **Códigos QR** como alternativa
- [ ] **Geolocalización** de escaneos

---

## 📞 Soporte

### Problemas Comunes

**P: El código no escanea**
R: Verificar que el lector esté configurado para Code128

**P: No aparecen las etiquetas en el PDF**
R: Primero hacer clic en "Generar Etiquetas Individuales"

**P: El operario no puede cambiar estado**
R: Verificar que esté logueado en el sistema

**P: ¿Puedo regenerar etiquetas?**
R: Sí, usar botón "Regenerar Etiquetas" (crea nuevos códigos)

---

## ✅ Checklist de Implementación

```
□ Ejecutar setup_produccion_barcode.php
□ Verificar creación de tabla
□ Configurar lector de códigos
□ Probar generación de etiquetas en orden de prueba
□ Imprimir etiquetas de prueba
□ Probar escaneo con lector
□ Capacitar operarios en uso del sistema
□ Establecer procedimiento de trabajo
□ Definir flujo de rechazos
□ Configurar estación de producción
□ Hacer prueba piloto con orden real
□ Ajustar según feedback
□ Implementación completa
```

---

**Versión:** 2.0  
**Fecha:** Febrero 2026  
**Sistema:** Órdenes de Producción - Control Granular
