# 🖼️ Sistema de Imágenes para Atributos Seleccionables

## Descripción

Ahora los atributos de tipo "Selección" pueden tener imágenes pequeñas asociadas a cada opción. Los clientes verán miniaturas visuales en lugar de un selector tradicional.

## Cambios Implementados

### Base de Datos
- ✅ **Nueva tabla**: `ecommerce_atributo_opciones`
  - Almacena cada opción del atributo con su imagen
  - Campos: id, atributo_id, nombre, imagen, orden
  - Relación: Muchas opciones por atributo

### Admin Panel
- ✅ **Interfaz mejorada** en `productos_atributos.php`
  - Botón "🖼️" en cada atributo de tipo select
  - Gestión de opciones con upload de imágenes
  - Vista de tarjetas mostrando opciones con miniaturas

### Frontend (Tienda)
- ✅ **Selector visual** en `producto.php`
  - Opciones se muestran como botones con imágenes
  - Sistema de radio buttons debajo (mantiene funcionalidad HTML)
  - Efecto visual al seleccionar (borde azul, fondo claro)
  - Fallback a selector tradicional si no hay imágenes

## Flujo de Uso

### Admin: Crear Atributo con Opciones

1. **Crear atributo base**
   - Ir a Productos → ⚙️ Atributos
   - Nombre: "Color"
   - Tipo: **Selección**
   - Costo Adicional: $0 (opcional)

2. **Agregar opciones con imágenes**
   - Clic en botón "🖼️" del atributo
   - Para cada opción:
     - Nombre: "Rojo"
     - Imagen: upload_red.jpg (80×80px recomendado)
     - Orden: 0, 1, 2...
   
3. **Resultado en tienda**
   - Cliente ve miniaturas de colores disponibles
   - Click en una = selecciona esa opción
   - Imagen se ilumina (borde azul)

## Estructura de Carpetas

```
uploads/
├── atributos/
│   ├── .htaccess          (permite acceso directo)
│   ├── atributo_5_1704067600.jpg
│   ├── atributo_5_1704067610.png
│   └── ... (una imagen por opción)
```

## Base de Datos - Ejemplos

### Crear tabla (migración)
```php
// migrar_atributo_opciones.php
CREATE TABLE ecommerce_atributo_opciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    atributo_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    imagen VARCHAR(255),
    orden INT DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (atributo_id) REFERENCES ecommerce_producto_atributos(id) ON DELETE CASCADE,
    INDEX (atributo_id)
)
```

### Consultar opciones de un atributo
```sql
SELECT * FROM ecommerce_atributo_opciones 
WHERE atributo_id = 15 
ORDER BY orden;
```

### Ver detalles de opciones con sus imágenes
```sql
SELECT 
    pa.nombre as atributo,
    ao.nombre as opcion,
    ao.imagen,
    ao.orden
FROM ecommerce_atributo_opciones ao
JOIN ecommerce_producto_atributos pa ON ao.atributo_id = pa.id
WHERE pa.producto_id = 3
ORDER BY pa.orden, ao.orden;
```

## JavaScript - Selección Visual

El frontend usa radio buttons con CSS visual:
- Cada opción es un `<label>` con imagen
- Click en imagen selecciona el radio button
- Borde y fondo cambian al seleccionar
- Evento `change` dispara `actualizarPrecio()`

## Especificaciones de Imágenes

| Aspecto | Recomendación |
|---------|---------------|
| **Tamaño** | 80×80 píxeles |
| **Formatos** | JPG, PNG, GIF, WEBP |
| **Peso máx** | 2MB por imagen |
| **Ruta** | `/uploads/atributos/` |
| **Thumbnail** | No necesario, se fuerza 80×80 con `object-fit: cover` |

## Ejemplo: Atributo "Color" para Almohada

```
Producto: Almohada Premium
Atributo: Color

Opciones:
├─ Rojo
│  └─ Imagen: atributo_12_1704067600.jpg
├─ Azul
│  └─ Imagen: atributo_12_1704067610.jpg
├─ Verde
│  └─ Imagen: atributo_12_1704067620.jpg
└─ Blanco
   └─ Imagen: atributo_12_1704067630.jpg
```

En tienda, cliente ve:
```
[Rojo]  [Azul]  [Verde]  [Blanco]
 🟥     🟦      🟩       ⬜
```

## Compatibilidad

- ✅ Atributos sin imágenes: muestran selector tradicional
- ✅ Atributos mixtos: solo algunas opciones con imagen
- ✅ Mobile friendly: flex layout responsive
- ✅ Fallback: si imagen no carga, muestra nombre
- ✅ Validación: obligatorio vs opcional

## Migración de Datos Existentes

Si tenías atributos con valores separados por coma:
1. Crear atributo nuevo de tipo select
2. Ir a 🖼️ Opciones
3. Agregar cada opción con su imagen
4. Sistema usa tabla nueva (no afecta datos antiguos)

## Validación

```javascript
// En producto.php
actualizarPrecio() {
    // Lee valores de select con imágenes
    const valor = document.getElementById('attr_X').value;
    
    // Calcula precio incluyendo costo del atributo
    // ...
}
```

## Próximas Mejoras Posibles

- [ ] Drag & drop para reordenar opciones
- [ ] Preview en tamaño real de selección múltiple
- [ ] Galería expandible de opciones
- [ ] Variantes de atributo (talla S/M/L con colores)
- [ ] Sincronización de imágenes con galería de producto

---

**Versión:** 1.0  
**Fecha:** 2024  
**Estado:** ✅ Completado
