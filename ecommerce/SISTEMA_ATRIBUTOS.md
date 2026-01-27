# 📦 Sistema de Atributos con Costos Adicionales - Documentación

## Descripción General

Se ha implementado un sistema completo de **atributos de productos con costos adicionales** que se suman al precio total. El sistema permite a los administradores crear atributos personalizados para cada producto, asignar costos a estos atributos, y el sistema calcula automáticamente el precio final incluyendo los costos adicionales.

## Cambios Realizados

### 1. **Base de Datos** 📊

#### Migraciones:
- **migrar_productos_v2.php**: Ya incluye la creación de la tabla `ecommerce_producto_atributos` con el campo `costo_adicional` (DECIMAL 10,2)
- **migrar_pedidos_atributos.php** (NUEVO): Agrega la columna `atributos` a la tabla `ecommerce_pedido_items` para almacenar JSON con detalles de atributos seleccionados

### Estructura de la tabla `ecommerce_producto_atributos`:
```sql
CREATE TABLE ecommerce_producto_atributos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    producto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    tipo ENUM('text', 'number', 'select') DEFAULT 'text',
    valores TEXT,
    costo_adicional DECIMAL(10, 2) DEFAULT 0,
    es_obligatorio TINYINT(1) DEFAULT 0,
    orden INT DEFAULT 0,
    FOREIGN KEY (producto_id) REFERENCES ecommerce_productos(id) ON DELETE CASCADE,
    INDEX (producto_id)
)
```

### 2. **Admin Panel** 🛠️

#### Archivos Modificados:

**productos_atributos.php**
- Form para crear/editar atributos
- Nuevo campo: **"Costo Adicional"** (number input, step 0.01, min 0)
- Tabla que muestra atributos con badges de costo: `+$X.XX` o "Gratis"

**productos_crear.php**
- Botón "⚙️ Atributos" (aparece después de crear el producto)

**productos.php**
- Botón "⚙️ Atributos" en cada fila de producto (acceso rápido)

**index.php** (Dashboard)
- Nueva tarjeta mostrando total de atributos registrados
- Contador actualizado junto a otros KPIs

### 3. **Frontend (Tienda)** 🛒

#### Archivo: **producto.php**

**Cambios en inputs de medidas:**
- ❌ ANTES: SELECT con opciones fijas de ancho/alto
- ✅ AHORA: INPUT type="number" (rango 10-300 cm)

**Cálculo de Precio Dinámico:**
1. Usuario ingresa alto y ancho (números)
2. Sistema busca el precio más cercano usando **Manhattan Distance**
3. Se suma el `costo_adicional` de cada atributo seleccionado
4. Se muestra el precio final actualizado en tiempo real
5. Si hay redondeo, se indica: "(Redondeado a medida más cercana: 150×220cm)"

**Ejemplo de Cálculo:**
```
Producto Cortina:
- Precio base: $100
- Matriz: 150×220cm = $500
- Usuario ingresa: 155×225cm → Sistema redondea a 150×220cm = $500

Si producto tiene atributos:
- Arandela de Aluminio: +$50
- Protección UV: +$30
- Precio final: $500 + $50 + $30 = $580
```

**JavaScript actualizado:**
```javascript
function actualizarPrecio() {
    // Busca el precio más cercano en la matriz
    // Suma costos de atributos con valor seleccionado
    // Actualiza precio en tiempo real
}

// Se ejecuta al:
// - Cambiar alto/ancho (onchange, onkeyup)
// - Seleccionar atributos (change)
```

### 4. **Carrito** 🛒

#### Archivo: **carrito.php**

**Cambios:**
- Calcula correctamente el subtotal incluyendo costos de atributos
- Muestra los atributos seleccionados con sus costos en cada línea
- Badge "+" verde mostrando costo de cada atributo

**Ejemplo de display:**
```
Cortina 150×220cm                          $500.00
Arandela Aluminio: Plata [+$50.00]
Protección UV: Sí [+$30.00]
Cantidad: 1                                $580.00
```

### 5. **Checkout** 💳

#### Archivo: **checkout.php**

**Cambios:**
- Calcula total correcto con atributos y costos
- Almacena JSON de atributos en BD junto al precio final
- Resumen muestra: precio base + descripción de atributos + total

**Estructura almacenada en BD:**
```json
{
    "atributos": [
        {
            "nombre": "Arandela",
            "valor": "Plata",
            "costo_adicional": 50.00
        },
        {
            "nombre": "Protección",
            "valor": "Sí",
            "costo_adicional": 30.00
        }
    ]
}
```

### 6. **Nuevo: Detalle de Pedidos** 📋

#### Archivo: **pedidos_detalle.php** (NUEVO)

- Visualiza detalles completos del pedido
- Muestra atributos seleccionados con sus costos
- Desglose claro de:
  - Producto
  - Medidas
  - Atributos seleccionados
  - Costos adicionales
  - Precio unitario final
  - Cantidad y subtotal

## Flujo de Uso

### Administrador:
1. Crear producto base (nombre, descripción, precio base)
2. Configurar matriz de precios (si es variable)
3. Ir a "⚙️ Atributos" desde el producto
4. Crear atributos:
   - Nombre (ej: "Arandela", "Protección")
   - Tipo (text, number, select)
   - Si select: valores separados por comas
   - Costo Adicional (ej: $50.00)
   - ¿Es obligatorio?

### Cliente:
1. Ve producto en tienda
2. Si es variable: ingresa alto y ancho (números)
3. Selecciona atributos deseados
4. Ve precio actualizado automáticamente
5. Agrega al carrito
6. En carrito ve desglose de costos
7. En checkout confirma detalles
8. Pedido se crea con todos los datos

### Admin (ver pedidos):
1. Admin → Pedidos
2. Click en "Ver" de un pedido
3. Ve detalles completos incluyendo atributos y sus costos

## Validaciones

✅ Atributos obligatorios se validan antes de agregar al carrito
✅ Medidas (10-300 cm) validadas en HTML5
✅ Costos deben ser ≥ 0
✅ Redondeo automático a medida más cercana

## Base Datos - Operaciones

### Verificar atributos de un producto:
```sql
SELECT * FROM ecommerce_producto_atributos 
WHERE producto_id = 1
ORDER BY orden;
```

### Ver detalles de un pedido con atributos:
```sql
SELECT pi.*, pr.nombre, pi.atributos
FROM ecommerce_pedido_items pi
LEFT JOIN ecommerce_productos pr ON pi.producto_id = pr.id
WHERE pi.pedido_id = 5;
```

### Calcular ingresos por atributos:
```sql
SELECT 
    COUNT(*) as pedidos_con_atributos,
    SUM(total) as ingresos_total
FROM ecommerce_pedidos
WHERE id IN (
    SELECT DISTINCT pedido_id 
    FROM ecommerce_pedido_items 
    WHERE atributos IS NOT NULL
);
```

## Archivos Modificados - Resumen

| Archivo | Cambio | Tipo |
|---------|--------|------|
| `ecommerce/admin/migrar_productos_v2.php` | Crea tabla con costo_adicional | Base de datos |
| `ecommerce/admin/migrar_pedidos_atributos.php` | NUEVO - Agrega columna atributos | Base de datos |
| `ecommerce/admin/productos_atributos.php` | Agrega input costo, muestra badge | Admin |
| `ecommerce/admin/productos_crear.php` | Botón ⚙️ después de guardar | Admin |
| `ecommerce/admin/productos.php` | Botón ⚙️ en cada fila | Admin |
| `ecommerce/admin/index.php` | Tarjeta de atributos totales | Admin |
| `ecommerce/admin/pedidos_detalle.php` | NUEVO - Muestra detalles con atributos | Admin |
| `ecommerce/producto.php` | Inputs numéricos, cálculo con atributos | Frontend |
| `ecommerce/carrito.php` | Cálculo y display de costos de atributos | Frontend |
| `ecommerce/checkout.php` | Almacena atributos en JSON | Frontend |

## Testing

### Caso 1: Producto Simple (sin matriz):
```
✓ Crear producto con precio base $100
✓ Crear atributo "Color" con +$20
✓ Cliente selecciona atributo → precio sube a $120
✓ Pedido se crea correctamente
```

### Caso 2: Producto Variable (con matriz):
```
✓ Crear producto cortina con matriz de precios
✓ Cliente ingresa 155×225cm → se redondea a 150×220cm ($500)
✓ Cliente selecciona "Arandela +$50"
✓ Precio final: $550
✓ Pedido muestra desglose correcto
```

### Caso 3: Atributo Obligatorio:
```
✓ Crear atributo "Tipo de Tela" como obligatorio
✓ Cliente intenta comprar sin seleccionar → error
✓ Cliente selecciona valor → permite compra
```

## URLs Importantes

- Admin Dashboard: `/ecommerce/admin/index.php`
- Admin Atributos: `/ecommerce/admin/productos_atributos.php?producto_id=X`
- Admin Pedidos: `/ecommerce/admin/pedidos.php`
- Admin Detalle Pedido: `/ecommerce/admin/pedidos_detalle.php?id=X`
- Tienda: `/ecommerce/index.php`
- Producto: `/ecommerce/producto.php?id=X`
- Carrito: `/ecommerce/carrito.php`
- Checkout: `/ecommerce/checkout.php`

## Notas Técnicas

- Todos los datos se escapan con `htmlspecialchars()`
- Las consultas usan prepared statements (secure)
- JSON se valida con `json_decode()`
- Precios se formatean con `number_format()` y `toLocaleString()`
- Manhattan Distance para búsqueda de precio más cercano
- Event listeners en JavaScript para actualizaciones en tiempo real

## Próximas Mejoras Posibles

- [ ] Agregar sugerencias de "tamaños populares" al escribir
- [ ] Autocomplete de medidas basado en matriz disponible
- [ ] Exportar pedidos a PDF con detalles de atributos
- [ ] Dashboard: gráficos de atributos más vendidos
- [ ] Sistema de descuentos basado en atributos seleccionados
- [ ] Validación de rango de medidas en servidor

---

**Versión:** 1.0  
**Fecha:** 2024  
**Estado:** ✅ Completado
