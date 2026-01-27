# Tucu Roller - Ecommerce

## Estructura del Proyecto

```
ecommerce/
├── config.php                 # Configuración (usa la misma DB que el sistema principal)
├── setup_ecommerce.php        # Script para crear las tablas
├── index.php                  # Página de inicio
├── tienda.php                 # Catálogo de productos
├── producto.php               # Detalle del producto con matriz de precios
├── carrito.php                # Carrito de compras
├── checkout.php               # Formulario de compra
├── nosotros.php               # Información de la empresa
├── contacto.php               # Formulario de contacto
├── includes/
│   ├── header.php             # Encabezado y navegación
│   └── footer.php             # Pie de página
├── assets/
│   └── style.css              # Estilos personalizados
└── uploads/                   # Imágenes de productos
```

## Instalación

### 1. Ejecutar setup
Accede a `/ecommerce/setup_ecommerce.php` en el navegador para crear las tablas:
- `ecommerce_categorias`
- `ecommerce_productos`
- `ecommerce_matriz_precios`
- `ecommerce_clientes`
- `ecommerce_pedidos`
- `ecommerce_pedido_items`
- `ecommerce_empresa`

### 2. Tablas Principales

#### ecommerce_categorias
- Categorías de productos (Cortinas, Toldos, Persianas, etc.)
- Cada categoría tiene nombre, descripción e icono

#### ecommerce_productos
- Productos con dos tipos de precio:
  - **Fijo**: Precio estándar
  - **Variable**: Precio basado en matriz de medidas (alto x ancho)

#### ecommerce_matriz_precios
- Tabla para cortinas y toldos
- Separada cada 10cm hasta 300cm
- Estructura: alto_cm x ancho_cm = precio
- Ejemplo:
  ```
  Alto  | Ancho | Precio
  10    | 10    | $500
  10    | 20    | $600
  20    | 10    | $550
  ...
  300   | 300   | $5000
  ```

#### ecommerce_clientes
- Email único por cliente
- Datos de envío (dirección, ciudad, provincia)

#### ecommerce_pedidos
- Número de pedido único
- Estados: pendiente, confirmado, preparando, enviado, entregado, cancelado
- Método de pago registrado

#### ecommerce_pedido_items
- Items individuales de cada pedido
- Guarda medidas si es producto variable

#### ecommerce_empresa
- Información de contacto
- Logo, redes sociales
- Términos y políticas

## Características

### 🛒 Carrito de Compras
- Sesión basada
- Soporte para productos con medidas
- Actualización de cantidades
- Estimación de envío

### 📏 Matriz de Precios
- Tabla visual para productos variables
- Cada 10cm hasta 300cm
- Selección fácil de medidas
- Validación de combinaciones disponibles

### 🔍 Búsqueda y Filtrado
- Búsqueda por nombre/descripción
- Filtro por categoría
- Vista de productos destacados

### 💳 Checkout Seguro
- Recolección de datos del cliente
- Múltiples métodos de pago
- Generación de pedidos con número único
- Confirmación de compra

## Configuración de Productos Variables

Para un producto de cortina/toldo:

1. **Crear Producto**
   - nombre: "Cortina Roller Blackout"
   - tipo_precio: "variable"
   - precio_base: 500 (precio mínimo)

2. **Agregar Matriz de Precios**
   ```sql
   INSERT INTO ecommerce_matriz_precios (producto_id, alto_cm, ancho_cm, precio)
   VALUES (1, 10, 10, 500);
   VALUES (1, 10, 20, 600);
   VALUES (1, 20, 10, 550);
   -- ... continuar hasta 300cm
   ```

3. **Generador de Matriz (SQL Helper)**
   ```sql
   -- Genera matriz cada 10cm desde 10 hasta 300
   INSERT INTO ecommerce_matriz_precios (producto_id, alto_cm, ancho_cm, precio)
   SELECT 1 as producto_id, a.num*10 as alto_cm, a.num*10 as ancho_cm, 
          (a.num*10 * a.num*10 / 10) as precio
   FROM (SELECT @n:=@n+1 as num FROM 
         (SELECT 0 UNION SELECT 1) a,
         (SELECT 0 UNION SELECT 1) b,
         (SELECT 0 UNION SELECT 1) c,
         (SELECT 0 UNION SELECT 1) d LIMIT 30) a
   WHERE a.num BETWEEN 1 AND 30;
   ```

## Páginas Principales

### index.php
- Bienvenida con información de empresa
- Productos destacados
- Ventajas competitivas
- Call-to-action hacia tienda

### tienda.php
- Catálogo completo de productos
- Filtrado por categoría
- Búsqueda de productos
- Grid responsive

### producto.php
- Detalle completo del producto
- Imagen y descripción
- Para productos variables: Matriz de precios interactiva
- Agregar al carrito con medidas

### carrito.php
- Listado de items seleccionados
- Edición de cantidades
- Cálculo de totales con envío
- Proceder al checkout

### checkout.php
- Formulario de datos del cliente
- Selección de método de pago
- Resumen del pedido
- Confirmación y generación de número de pedido

## Funcionalidades Futuras

- [ ] Pasarela de pago integrada
- [ ] Sistema de cupones/descuentos
- [ ] Wishlist/Favoritos
- [ ] Comentarios y calificaciones
- [ ] Gestión de stock
- [ ] Panel de admin para gestionar catálogo
- [ ] Integración con redes sociales
- [ ] Email de confirmación
- [ ] Seguimiento de pedidos
- [ ] Sistema de recomendaciones

## Notas Importantes

- Usa la misma base de datos que el sistema administrativo
- Las sesiones están configuradas para el carrito
- Los precios se manejan en DECIMAL(10,2)
- Compatible con Bootstrap 5.3
- Responsive design incluido
