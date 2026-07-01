# Guía - Módulo de Notas de Crédito

## Descripción General

El módulo de Notas de Crédito permite gestionar devoluciones, ajustes de precio y otros ajustes de facturación de forma centralizada. Integra soporte tanto para **notas de crédito fiscales** (emitidas con AFIP tipo 03) como para **recibos internos simples**.

## Características

### 1. Tipos de Notas de Crédito

- **Nota de Crédito Fiscal (Tipo 03, 08, 13)**: Comprobante oficial emitido ante AFIP
  - Vinculada a una factura original
  - Genera número secuencial automático
  - Opcionalmente emite CAE (Código de Autorización Electrónica)
  
- **Recibo Interno**: Documento simple sin conexión a AFIP
  - Para devoluciones y ajustes menores
  - Más rápido de procesar
  - Sin requerimientos fiscales

### 2. Motivos Predefinidos

- **Devolución de producto**: Cuando el cliente devuelve parte o todo lo vendido
- **Ajuste de precio**: Corrección de precios incorrectamente facturados
- **Descuento adicional**: Bonificación posterior a la facturación
- **Cancelación parcial**: Crédito por cancelación anticipada
- **Error en facturación**: Corrección de errores administrativos
- **Otro**: Motivo personalizado

### 3. Estados de la Nota

1. **Borrador** (amarillo)
   - Nota en preparación
   - Se pueden agregar/eliminar items
   - Se puede emitir o cancelar

2. **Emitida** (verde)
   - Nota procesada y registrada
   - Ya no se puede modificar
   - Disponible para descargar como PDF

3. **Cancelada** (rojo)
   - Nota descartada
   - No genera efecto en la facturación

## Flujo de Uso

### Paso 1: Crear una Nueva Nota de Crédito

1. Ir a **Admin → Notas de Crédito**
2. Hacer click en **"➕ Nueva Nota de Crédito"**
3. Se abre un modal con los siguientes campos:
   - **Pedido/Factura** (obligatorio): Seleccionar el pedido original
   - **Tipo de Comprobante**: 
     - "Nota de Crédito Fiscal" (por defecto)
     - "Recibo Interno"
   - **Tipo NC** (si es fiscal): Seleccionar tipo 03/A, 08/B o 13/C
   - **Monto Total de Crédito** (obligatorio): El monto completo de la NC
   - **Motivo** (obligatorio): Razón de la nota
   - **Descripción**: Detalles adicionales (opcional)

### Paso 2: Agregar Items (si aplica)

Si la NC es **por items específicos** (no por monto global):

1. Hacer click en **"Ver"** en el listado o desde la creación
2. En la sección **"Items de Nota de Crédito"**:
   - Click en **"➕ Agregar Item"**
   - Opción A: Seleccionar un item del pedido original (se auto-completa)
   - Opción B: Ingresar manualmente descripción, cantidad y precio
3. El monto total se recalcula automáticamente
4. Se pueden eliminar items si es necesario

### Paso 3: Emitir la Nota

1. Desde el detalle de la NC, click en **"✓ Emitir Nota de Crédito"**
2. Se confirma la acción
3. La NC pasa a estado **"Emitida"**
4. Se genera número de NC secuencial automático (Ej: 03-0001-00000001)

**En caso de NC Fiscal**: Se prepara para envío a AFIP (se puede integrar solicitud de CAE)

**En caso de Recibo Interno**: Se registra inmediatamente

### Paso 4: Descargar PDF

1. Hacer click en **"📄 Ver PDF"**
2. Se genera automáticamente un documento con:
   - Número de NC
   - Datos empresa/cliente
   - Items y monto
   - CAE (si aplica)
   - Fecha de emisión

## Filtros y Búsqueda

En la página principal de Notas de Crédito se pueden filtrar por:

- **Número NC**: Búsqueda parcial del número
- **Estado**: Borrador, Emitida, Cancelada
- **Pedido ID**: ID del pedido original

## Integración con AFIP (Nivel Avanzado)

### Próximas Mejoras Planificadas

El sistema está preparado para futura integración con AFIP:

1. **Validación de Factura Original**: Verificar que existe CAE en la factura de origen
2. **Solicitud de CAE**: Envío automático a AFIP tipo `FERecuperatorXML` para NC tipo 03
3. **Validación de Número**: Verificar consecutividad de números
4. **Estado de Envío**: Mostrar si la NC fue aceptada/rechazada por AFIP

Por ahora, puede usar:
- **NC Fiscal sin CAE**: Para registro previo a una futura integración
- **Recibo Interno**: Para todas las devoluciones menores sin AFIP

## Casos de Uso

### Caso 1: Devolución Completa de Pedido

```
Pedido #1234 → Factura FA-0001-00000001 → Monto: $10,000
Cliente devuelve todo

→ Crear NC Fiscal
  - Tipo: 03 (NC A)
  - Monto: $10,000
  - Motivo: "Devolución de producto"
  - Emitir como PDF
```

### Caso 2: Ajuste de Precio

```
Factura: $5,000 (con error de cálculo de IVA)
Corrección: -$250 en impuestos

→ Crear NC Recibo Interno
  - Comprobante: "Recibo Interno"
  - Monto: $250
  - Motivo: "Error en facturación"
  - Emitir para registro
```

### Caso 3: Descuento Posterior

```
Cliente solicita descuento adicional: 5% sobre pedido de $2,000 = $100

→ Crear NC Fiscal
  - Tipo: 03
  - Monto: $100
  - Motivo: "Descuento adicional"
  - Con items: 1x "Descuento 5% aplicado" $100
```

## Campos en Base de Datos

### Tabla: ecommerce_notas_credito

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | ID único |
| pedido_id | INT | Referencia al pedido |
| numero_nc | VARCHAR(50) | Formato: TT-PPPP-NNNNNNNN |
| tipo_nc | VARCHAR(5) | 03/08/13 (solo si es fiscal) |
| monto_total | DECIMAL(12,2) | Total de la NC |
| motivo | VARCHAR(200) | Razón de la NC |
| estado | ENUM | borrador/emitida/cancelada |
| comprobante_tipo | VARCHAR(20) | factura/recibo |
| cae | VARCHAR(20) | Código de autorización AFIP |
| fecha_emision | DATETIME | Cuándo fue emitida |
| created_at | DATETIME | Fecha de creación |

### Tabla: ecommerce_notas_credito_items

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | ID único |
| nota_credito_id | INT | Referencia a NC |
| pedido_item_id | INT | Item original (opcional) |
| descripcion | VARCHAR(255) | Descripción del item |
| cantidad | DECIMAL(10,2) | Cantidad |
| precio_unitario | DECIMAL(12,2) | Precio unitario |
| subtotal | DECIMAL(12,2) | Cantidad × Precio |

## Permisos

El módulo verifica el permiso `nota_credito`. Se puede gestionar en:
- **Admin → Usuarios → Editar usuario → Permisos**

Incluye:
- Ver listado de notas de crédito
- Crear nuevas notas
- Editar notas en borrador
- Emitir notas
- Descargar PDFs

## Troubleshooting

### Problema: "Nota de crédito no encontrada"

**Solución**: Verificar que:
1. El ID de la NC existe en la URL
2. El usuario tiene permisos (`nota_credito`)
3. La base de datos se inicializó correctamente

### Problema: "Pedido no encontrado"

**Solución**: 
1. Verificar que el pedido existe
2. El número de factura no es null
3. Usar la opción de búsqueda en el modal

### Problema: Monto Total no se Recalcula

**Solución**:
1. Actualizar la página (F5)
2. Verificar que se agregaron items correctamente
3. Ver el navegador console (F12) para errores de JavaScript

## Archivos del Módulo

```
ecommerce/admin/
├── nota_credito.php              # Listado y creación
├── nota_credito_detalle.php      # Detalles y edición
├── nota_credito_pdf.php          # Generación de PDF
└── includes/
    └── nota_credito_helper.php   # Funciones comunes y BD
```

## Próximos Pasos

1. ✅ Crear notas de crédito (borrador/emitida)
2. ✅ Gestionar items
3. ✅ Generar PDFs
4. ⏳ Integración con AFIP (validación y CAE)
5. ⏳ Reportes de notas emitidas
6. ⏳ Aplicar automáticamente como crédito en cuenta del cliente
7. ⏳ Auditoría y trazabilidad

---

**Versión**: 1.0  
**Última actualización**: 2026-04-13  
**Desarrollador**: Sistema de Administración Etiqueta
