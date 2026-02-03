# 💰 Precios Especiales por Opción de Atributos

## Descripción General

Se ha mejorado el sistema de atributos para permitir asignar **precios especiales a cada opción** de los atributos. Esto permite que los clientes vean exactamente cuánto cuesta cada opción al seleccionarla, y el sistema calcula automáticamente el precio final incluyendo estos costos adicionales.

## Características Implementadas

### 1. **Panel Administrativo** 🛠️

#### Admin > Productos > Atributos > Opciones

**Nuevas mejoras:**
- ✅ Cada opción muestra un **badge verde con el costo especial**: `+$50.00`
- ✅ Si la opción es gratis, muestra: `Gratis` (badge gris)
- ✅ Campo de entrada dedicado para "Costo adicional ($)" en cada opción
- ✅ La tabla de opciones muestra visualmente el precio especial de cada una

**Ejemplo visual:**
```
┌─────────────────────────────┐
│ Arandela de Aluminio        │
│ [Imagen o color]            │
│                      +$50.00 │ ← Badge con precio
└─────────────────────────────┘
```

### 2. **Tienda (Frontend)** 🛒

#### Página de Producto

**Visualización de opciones mejorada:**
- ✅ Cada opción de atributo tipo "select" muestra un badge con el costo especial
- ✅ Badge posicionado en la esquina superior derecha de cada opción
- ✅ Color verde para facilitar identificación del costo
- ✅ Se actualiza automáticamente al seleccionar una opción

**Desglose de precios en tiempo real:**
- ✅ El precio total se actualiza en vivo al seleccionar opciones
- ✅ Se muestra el desglose de costos especiales: `+ Arandela: $50.00`
- ✅ Ejemplo de visualización:
  ```
  Precio: $500.00
  + Arandela de Aluminio: $50.00
  + Protección UV: $30.00
  ```

### 3. **Cálculo de Precios Mejorado** 📊

**Función `actualizarPrecio()`:**

El JavaScript ahora realiza un cálculo más transparente:

```javascript
precioTotal = precioBase
+ costoMedidas (si es variable)
- descuentos (si aplica)
+ costoOpcion1 (si está seleccionada)
+ costoOpcion2 (si está seleccionada)
────────────────────────────
= PRECIO FINAL
```

**Ejemplo de cálculo:**
```
Producto: Cortina
├─ Precio base: $100.00
├─ Medidas (150×220cm): +$400.00 (total $500.00)
├─ Arandela Aluminio: +$50.00
├─ Protección UV: +$30.00
└─ TOTAL: $580.00
```

### 4. **Carrito de Compras** 🛒

**Mejoras en la visualización:**
- ✅ Columna "Precio" ahora muestra el desglose:
  - Precio base del producto
  - `+$X.XX` para cada opción con costo
  - Precio final en verde
- ✅ Se mantiene el badge de costo en cada atributo seleccionado
- ✅ El subtotal calcula correctamente incluyendo todos los costos especiales

**Ejemplo en el carrito:**
```
Producto      | Precio              | Cantidad | Subtotal
Cortina       | $500.00             | 1        | $580.00
              | +$50.00 (Arandela)  |          |
              | +$30.00 (UV)        |          |
              | $580.00             |          |
```

## Campos de la Base de Datos

### Tabla: `ecommerce_atributo_opciones`

```sql
CREATE TABLE ecommerce_atributo_opciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    atributo_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    imagen VARCHAR(255),
    color VARCHAR(7),
    costo_adicional DECIMAL(10,2) DEFAULT 0,  ← NUEVO CAMPO MEJORADO
    orden INT DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (atributo_id) REFERENCES ecommerce_producto_atributos(id)
)
```

**Campo `costo_adicional`:**
- Tipo: `DECIMAL(10,2)` - permite hasta $9,999,999.99
- Valor por defecto: `0` (opción gratis)
- Se suma automáticamente al precio total cuando se selecciona la opción

## Archivos Modificados

### 1. **ecommerce/admin/productos_atributos.php**
- ✅ Mejorada visualización de opciones con badges de costo
- ✅ Ahora muestra claramente el precio especial de cada opción
- ✅ Campos de entrada para asignar costos a opciones

### 2. **ecommerce/producto.php**
- ✅ Badges visuales con costos especiales en cada opción
- ✅ Desglose de costos en tiempo real bajo el precio
- ✅ Función `actualizarPrecio()` mejorada con desglose de costos

### 3. **ecommerce/carrito.php**
- ✅ Visualización mejorada del desglose de precios por opción
- ✅ Mejor claridad en el precio unitario vs. precio con opciones

## Ejemplo de Uso

### Caso 1: Cortina con opciones de accesorios

**Admin configura:**
1. Crea atributo "Accesorios" tipo "select"
2. Agrega opción "Arandela Aluminio" con costo +$50.00
3. Agrega opción "Protección UV" con costo +$30.00
4. Agrega opción "Sin accesorios" con costo $0.00

**Cliente ve en tienda:**
```
Selecciona: "Arandela Aluminio" → Precio sube a $550.00
Selecciona: "Protección UV" también → Precio sube a $580.00
```

### Caso 2: Producto con múltiples atributos con costos

**Admin configura:**
- Atributo 1: "Color" con opciones (sin costo)
- Atributo 2: "Acabado" con:
  - Mate: $0.00
  - Brillante: +$20.00
  - Espejo: +$50.00

**Cliente ve en tienda:**
```
Precio base: $100.00
+ Acabado Espejo: $50.00
= TOTAL: $150.00
```

## Ventajas del Sistema

✅ **Transparencia** - Los clientes ven exactamente cuánto cuesta cada opción
✅ **Automatización** - Los precios se calculan automáticamente
✅ **Flexibilidad** - Cada opción puede tener un costo diferente
✅ **Escalabilidad** - Soporta múltiples atributos con múltiples opciones
✅ **Desglose claro** - El cliente ve el desglose de costos en tiempo real

## Notas Técnicas

### Almacenamiento en Sesión
Los costos se almacenan en la sesión cuando se agrega al carrito:
```php
$_SESSION['carrito'][$key]['atributos'] = [
    [
        'nombre' => 'Arandela',
        'valor' => 'Aluminio',
        'costo_adicional' => 50.00
    ]
];
```

### Cálculo de Subtotal
```php
$subtotal += $precio_item * $cantidad;
// Donde $precio_item incluye el precio base + costos de opciones
```

### Validación en Checkout
Los costos se validan nuevamente al procesar el pedido para evitar manipulaciones en el cliente.

## Próximas Mejoras (Opcionales)

- [ ] Reporte de opciones más usadas y sus ingresos por costos especiales
- [ ] Estadísticas de impacto de cada opción en los ingresos
- [ ] A/B testing de diferentes precios de opciones
- [ ] Cupones descuento específicos para opciones

---

**Última actualización:** 3 de Febrero, 2026
**Versión:** 1.0
