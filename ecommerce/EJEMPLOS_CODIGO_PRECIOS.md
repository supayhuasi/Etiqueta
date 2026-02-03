# 💻 Ejemplos de Código - Sistema de Precios por Opción

## 1. Obtener Opciones con Costos (Backend)

### PHP - Consulta Básica
```php
<?php
require 'config.php';

$atributo_id = 5;

// Obtener opciones con sus costos
$stmt = $pdo->prepare("
    SELECT id, nombre, costo_adicional, imagen, color
    FROM ecommerce_atributo_opciones 
    WHERE atributo_id = ? 
    ORDER BY orden
");
$stmt->execute([$atributo_id]);
$opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Resultado:
// [
//     ['id' => 1, 'nombre' => 'Arandela', 'costo_adicional' => 50.00],
//     ['id' => 2, 'nombre' => 'UV', 'costo_adicional' => 30.00]
// ]
?>
```

### PHP - Con Atributos
```php
<?php
// Obtener atributo con sus opciones
$stmt = $pdo->prepare("
    SELECT a.*, 
           GROUP_CONCAT(
               JSON_OBJECT(
                   'id', o.id,
                   'nombre', o.nombre,
                   'costo', o.costo_adicional
               )
           ) as opciones_json
    FROM ecommerce_producto_atributos a
    LEFT JOIN ecommerce_atributo_opciones o ON a.id = o.atributo_id
    WHERE a.id = ?
    GROUP BY a.id
");
$stmt->execute([$atributo_id]);
$atributo = $stmt->fetch(PDO::FETCH_ASSOC);
?>
```

## 2. Mostrar Opciones en HTML

### Con Badges de Costo
```html
<?php foreach ($opciones as $opcion): ?>
    <div class="opcion-item">
        <label>
            <input type="radio" name="attr_<?= $attr_id ?>" 
                   value="<?= htmlspecialchars($opcion['nombre']) ?>"
                   data-costo="<?= (float)$opcion['costo_adicional'] ?>"
                   onchange="actualizarPrecio()">
            
            <div class="opcion-contenido">
                <?php if ($opcion['imagen']): ?>
                    <img src="/uploads/atributos/<?= htmlspecialchars($opcion['imagen']) ?>" 
                         alt="<?= htmlspecialchars($opcion['nombre']) ?>">
                <?php endif; ?>
                
                <span><?= htmlspecialchars($opcion['nombre']) ?></span>
                
                <?php if ($opcion['costo_adicional'] > 0): ?>
                    <span class="badge bg-success">
                        +$<?= number_format($opcion['costo_adicional'], 2) ?>
                    </span>
                <?php else: ?>
                    <span class="badge bg-light">Gratis</span>
                <?php endif; ?>
            </div>
        </label>
    </div>
<?php endforeach; ?>
```

## 3. JavaScript - Cálculo de Precio

### Función Completa
```javascript
const atributosData = [
    { id: 1, nombre: 'Accesorios', costo_adicional: 0 },
    { id: 2, nombre: 'Acabado', costo_adicional: 0 }
];

const precioBase = 500.00;

function actualizarPrecio() {
    let precioFinal = precioBase;
    let costosAdicionales = [];
    
    // Recorrer atributos
    atributosData.forEach(attr => {
        const valorInput = document.getElementById('attr_' + attr.id);
        
        if (valorInput && valorInput.value) {
            // Obtener costo de la opción seleccionada
            const costoHidden = document.getElementById('attr_costo_' + attr.id);
            const costoOpcion = costoHidden ? 
                parseFloat(costoHidden.value || 0) : 0;
            
            // Sumar costo
            if (costoOpcion > 0) {
                precioFinal += costoOpcion;
                costosAdicionales.push({
                    nombre: valorInput.value,
                    costo: costoOpcion
                });
            }
        }
    });
    
    // Actualizar display
    mostrarPrecio(precioFinal, costosAdicionales);
}

function mostrarPrecio(precioFinal, costos) {
    const precioFormatado = precioFinal.toLocaleString('es-AR', {
        style: 'currency',
        currency: 'ARS'
    });
    
    let html = `Precio: <strong>${precioFormatado}</strong>`;
    
    if (costos.length > 0) {
        html += '<br><small class="text-muted">';
        costos.forEach(c => {
            html += `<span class="badge bg-light text-dark">
                        + ${c.nombre}: $${c.costo.toFixed(2)}
                    </span>`;
        });
        html += '</small>';
    }
    
    document.getElementById('precio_display').innerHTML = html;
}
```

### Versión Simplificada
```javascript
function actualizarPrecio() {
    let total = precioBase;
    
    // Sumar costos de opciones seleccionadas
    document.querySelectorAll('[data-costo]').forEach(input => {
        if (input.checked) {
            total += parseFloat(input.dataset.costo || 0);
        }
    });
    
    document.getElementById('precio_display').textContent = 
        'Precio: $' + total.toFixed(2);
}
```

## 4. Guardar en Carrito (Sesión)

### Agregar Producto con Opciones
```php
<?php
// Obtener datos del formulario
$producto_id = $_POST['producto_id'];
$cantidad = intval($_POST['cantidad'] ?? 1);
$atributos_seleccionados = [];

// Procesar atributos
foreach ($_POST as $key => $value) {
    if (strpos($key, 'attr_') === 0 && !empty($value)) {
        $attr_id = intval(substr($key, 5)); // Extraer ID
        $costo_key = 'attr_costo_' . $attr_id;
        $costo = isset($_POST[$costo_key]) ? 
            floatval($_POST[$costo_key]) : 0;
        
        $atributos_seleccionados[] = [
            'id' => $attr_id,
            'nombre' => 'Nombre del Atributo', // Obtener de BD
            'valor' => $value,
            'costo_adicional' => $costo
        ];
    }
}

// Crear item de carrito
$item = [
    'id' => $producto_id,
    'nombre' => $producto['nombre'],
    'precio' => $producto['precio'],
    'cantidad' => $cantidad,
    'atributos' => $atributos_seleccionados
];

// Agregar a sesión
$key = md5($producto_id . json_encode($atributos_seleccionados));
$_SESSION['carrito'][$key] = $item;
?>
```

## 5. Calcular Precio Total en Carrito

### Función en PHP
```php
<?php
function calcularPrecioItem($item) {
    $precio = $item['precio'];
    
    // Sumar costos de atributos
    if (isset($item['atributos']) && is_array($item['atributos'])) {
        foreach ($item['atributos'] as $attr) {
            if (isset($attr['costo_adicional'])) {
                $precio += floatval($attr['costo_adicional']);
            }
        }
    }
    
    return $precio;
}

function calcularSubtotal($carrito) {
    $subtotal = 0;
    foreach ($carrito as $item) {
        $precio_item = calcularPrecioItem($item);
        $subtotal += $precio_item * $item['cantidad'];
    }
    return $subtotal;
}

// Uso
$carrito = $_SESSION['carrito'] ?? [];
$subtotal = calcularSubtotal($carrito);
?>
```

## 6. Mostrar Desglose en Carrito

### HTML
```html
<?php foreach ($carrito as $item): 
    $precio_item = $item['precio'];
    $costo_atributos = 0;
    
    if (isset($item['atributos'])) {
        foreach ($item['atributos'] as $attr) {
            $costo_atributos += floatval($attr['costo_adicional'] ?? 0);
        }
    }
    
    $precio_final = $precio_item + $costo_atributos;
?>
    <tr>
        <td>
            <strong><?= htmlspecialchars($item['nombre']) ?></strong>
            <?php if (isset($item['atributos'])): ?>
                <div class="text-muted">
                    <?php foreach ($item['atributos'] as $attr): ?>
                        <small>
                            <?= htmlspecialchars($attr['valor']) ?>
                            <?php if ($attr['costo_adicional'] > 0): ?>
                                <span class="badge bg-success">
                                    +$<?= number_format($attr['costo_adicional'], 2) ?>
                                </span>
                            <?php endif; ?>
                        </small>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </td>
        <td>
            <div>$<?= number_format($precio_item, 2) ?></div>
            <?php if ($costo_atributos > 0): ?>
                <small class="text-muted">
                    +$<?= number_format($costo_atributos, 2) ?>
                </small>
                <small class="text-success fw-bold">
                    $<?= number_format($precio_final, 2) ?>
                </small>
            <?php endif; ?>
        </td>
        <td>$<?= number_format($precio_final * $item['cantidad'], 2) ?></td>
    </tr>
<?php endforeach; ?>
```

## 7. Validar en Checkout

### Recalcular Precios
```php
<?php
function validarYRecalcularPrecios($pdo, $carrito) {
    $resultado = [
        'valido' => true,
        'mensaje' => '',
        'subtotal' => 0
    ];
    
    foreach ($carrito as $item_key => $item) {
        // Obtener producto
        $stmt = $pdo->prepare("SELECT precio FROM ecommerce_productos WHERE id = ?");
        $stmt->execute([$item['id']]);
        $producto = $stmt->fetch();
        
        if (!$producto) {
            $resultado['valido'] = false;
            $resultado['mensaje'] = "Producto no encontrado";
            return $resultado;
        }
        
        // Recalcular precio
        $precio_esperado = $producto['precio'];
        
        // Validar y sumar atributos
        if (isset($item['atributos'])) {
            foreach ($item['atributos'] as $attr) {
                // Validar costo en BD
                $stmt = $pdo->prepare("
                    SELECT costo_adicional 
                    FROM ecommerce_atributo_opciones 
                    WHERE id = ? AND nombre = ?
                ");
                $stmt->execute([$attr['id'], $attr['valor']]);
                $opcion = $stmt->fetch();
                
                if ($opcion) {
                    $precio_esperado += floatval($opcion['costo_adicional']);
                }
            }
        }
        
        // Verificar que coincida con lo enviado
        $precio_calculado = calcularPrecioItem($item);
        
        if (abs($precio_esperado - $precio_calculado) > 0.01) {
            $resultado['valido'] = false;
            $resultado['mensaje'] = "Discrepancia en precio del producto";
            return $resultado;
        }
        
        $resultado['subtotal'] += $precio_calculado * $item['cantidad'];
    }
    
    return $resultado;
}

// Uso en checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validacion = validarYRecalcularPrecios($pdo, $_SESSION['carrito']);
    
    if (!$validacion['valido']) {
        $error = $validacion['mensaje'];
    } else {
        $subtotal = $validacion['subtotal'];
        // Procesar pago...
    }
}
?>
```

## 8. Query SQL - Ejemplos

### Opciones con costo > 0
```sql
SELECT id, nombre, costo_adicional
FROM ecommerce_atributo_opciones
WHERE costo_adicional > 0
ORDER BY costo_adicional DESC;
```

### Atributos con opciones y costos
```sql
SELECT 
    a.id,
    a.nombre as atributo,
    o.id as opcion_id,
    o.nombre as opcion_nombre,
    o.costo_adicional
FROM ecommerce_producto_atributos a
LEFT JOIN ecommerce_atributo_opciones o ON a.id = o.atributo_id
WHERE a.producto_id = ?
ORDER BY a.orden, o.orden;
```

### Ingresos por opciones (estadísticas)
```sql
SELECT 
    o.nombre,
    COUNT(*) as veces_seleccionada,
    SUM(o.costo_adicional) as ingresos_totales
FROM ecommerce_pedido_items pi
JOIN ecommerce_atributo_opciones o ON pi.atributos LIKE CONCAT('%', o.nombre, '%')
GROUP BY o.id
ORDER BY ingresos_totales DESC;
```

## 9. API JSON - Obtener Opciones

### Endpoint
```php
<?php
// GET /api/atributo-opciones.php?atributo_id=5

$atributo_id = intval($_GET['atributo_id'] ?? 0);

if ($atributo_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, nombre, costo_adicional, imagen, color
    FROM ecommerce_atributo_opciones
    WHERE atributo_id = ?
    ORDER BY orden
");
$stmt->execute([$atributo_id]);
$opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'opciones' => $opciones,
    'total_costo' => array_sum(array_column($opciones, 'costo_adicional'))
]);
?>
```

### Respuesta JSON
```json
{
  "opciones": [
    {
      "id": 1,
      "nombre": "Arandela Aluminio",
      "costo_adicional": 50.00,
      "imagen": "atributo_5_123456.jpg",
      "color": "#FF0000"
    },
    {
      "id": 2,
      "nombre": "Protección UV",
      "costo_adicional": 30.00,
      "imagen": null,
      "color": "#0000FF"
    }
  ],
  "total_costo": 80.00
}
```

---

**Ejemplos de Código:** 3 de Febrero, 2026
