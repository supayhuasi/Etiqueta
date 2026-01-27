# Guía de Mejoras v2 - Ecommerce

## Nuevas Funcionalidades

### 1. **Subcategorías**
Las categorías ahora pueden tener categorías padre, permitiendo una estructura jerárquica.

**En el admin:**
- Ir a **Categorías**
- Al crear/editar, selecciona una "Categoría Padre" para hacerla subcategoría
- Las subcategorías se mostrarán dentro de su categoría padre

### 2. **Galería de Imágenes Múltiples**
Los productos pueden tener varias imágenes con un slider automático.

**En el admin:**
1. Crea o edita un producto
2. Haz clic en el botón **🖼️ (Galería)** en el listado de productos
3. En `productos_imagenes.php`:
   - **Subir Nueva Imagen** - Carga imágenes (PNG, JPG, GIF - máx 5MB)
   - **Marcar como Principal** - Define cuál se ve primero
   - **Cambiar Orden** - Usa ↑ y ↓ para ordenar
   - **Eliminar** - Borra imágenes que no necesites

**En el frontend:**
- La página de producto muestra un carousel de imágenes
- Puedes navegar con los botones de flechas
- Haz clic en las miniaturas para cambiar de imagen rápidamente

### 3. **Atributos de Productos**
Define propiedades personalizadas para cada producto (color, material, tamaño, etc.).

**En el admin:**
1. Accede a un producto de tipo **variable** (cortinas, toldos)
2. Haz clic en el botón **Atributos**
3. En `productos_atributos.php`:
   - **Tipos de atributo:**
     - `Text` - Entrada de texto libre
     - `Number` - Campo numérico
     - `Select` - Desplegable con opciones
   - Marca como **Obligatorio** si el cliente debe completarlo
   - Define el **Orden** de aparición

**Ejemplos de atributos:**
- Color (Select): "Blanco, Negro, Gris"
- Tela (Select): "Lino, Tela Acrílica, Blackout"
- Especificaciones (Text): Anotaciones especiales
- Cantidad de paneles (Number): 1, 2, 3, etc.

**En el frontend:**
- Los atributos aparecen en el formulario del producto
- Los obligatorios deben completarse antes de agregar al carrito
- Se guardan con cada item en el carrito

### 4. **Cálculo Dinámico de Precio**
El sistema calcula el precio automáticamente según medidas, redondeando a la medida más cercana cargada.

**Cómo funciona:**
1. Cliente selecciona Alto y Ancho en cm
2. El sistema busca la combinación exacta en la matriz
3. Si no existe, **redondea a la medida más cercana**
4. Muestra el precio calculado en tiempo real
5. Indica qué medida exacta se está usando ("Redondeado a 200×150cm")

**Ejemplo:**
- Cliente pide: 155 × 225 cm
- Matriz tiene: 150×220cm ($X), 150×230cm ($Y), 160×220cm ($Z)
- Sistema elige 150×220cm (menor distancia)
- Precio: $X y muestra "(Redondeado a 150×220cm)"

### 5. **Matriz de Precios Mejorada**
La matriz ahora se muestra expandible en el producto con opción de ver todos los precios.

**En la página del producto:**
- Accordion plegable con "Ver Matriz de Precios Completa"
- Tabla bidimensional: filas = altos, columnas = anchos
- Todos los precios visibles para comparar

## Migración de Datos

Antes de usar estas funcionalidades, ejecuta:

```
ecommerce/admin/migrar_productos_v2.php
```

Este script:
- Crea las nuevas tablas (product_imagenes, producto_atributos)
- Migra las imágenes actuales a la nueva tabla
- Mantiene compatibilidad con datos existentes

## Flujo Completo de Uso

### **Para Admin:**
1. Ejecutar `migrar_productos_v2.php`
2. Organizar categorías (crear subcategorías si es necesario)
3. Para cada producto:
   - Crear/editar datos básicos
   - Cargar múltiples imágenes (galería)
   - Agregar atributos (si es tipo variable)
   - Generar/gestionar matriz de precios

### **Para Cliente:**
1. Navega por categorías (incluyendo subcategorías)
2. Selecciona un producto
3. Ve galería de imágenes
4. Completa atributos obligatorios (si existen)
5. Para productos variable:
   - Selecciona Alto y Ancho
   - Ve precio calculado en tiempo real
   - Nota cuál medida exacta se está usando
6. Agrega al carrito

## Base de Datos - Nuevas Tablas

### `ecommerce_producto_imagenes`
```sql
- id (INT PRIMARY KEY)
- producto_id (INT)
- imagen (VARCHAR)
- orden (INT) - para ordenar
- es_principal (TINYINT) - imagen destacada
- fecha_creacion (TIMESTAMP)
```

### `ecommerce_producto_atributos`
```sql
- id (INT PRIMARY KEY)
- producto_id (INT)
- nombre (VARCHAR)
- tipo (ENUM: text, number, select)
- valores (TEXT) - opciones separadas por coma
- es_obligatorio (TINYINT)
- orden (INT)
```

### Cambios en `ecommerce_categorias`
```sql
+ parent_id (INT) - referencia a categoría padre
```

## Notas Técnicas

- Los atributos se guardan como JSON en la sesión del carrito
- El cálculo de distancia usa suma de valores absolutos (Manhattan distance)
- Las imágenes se almacenan en `/ecommerce/uploads/` con nombre `prod_PRODUCTOID_TIMESTAMP.ext`
- Bootstrap 5.3 Carousel implementado nativo sin librerías externas
- La selección de atributos y medidas se valida lado servidor

## Troubleshooting

### "Tabla no encontrada ecommerce_producto_imagenes"
→ Ejecuta `migrar_productos_v2.php`

### Las imágenes no se cargan
→ Verifica permisos de carpeta `/ecommerce/uploads/`
→ Verifica que sea PNG, JPG o GIF
→ Máximo 5MB

### El precio no se actualiza
→ Asegúrate de que exista matriz de precios
→ JavaScript habilitado en navegador
→ Recarga la página

### Los atributos no aparecen
→ Verifica que producto sea tipo "variable"
→ Agrégalos desde el botón en admin
→ Recarga navegador (Ctrl+F5)
