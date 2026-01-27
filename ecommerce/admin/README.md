# Panel de Administración - Ecommerce

## Acceso
- URL: `https://tucuroller.com/ecommerce/admin/`
- Usuario: Utiliza el mismo login del sistema principal (rol = admin)
- Autenticación: Compartida con el sistema principal mediante `$_SESSION`

## 🔧 Migraciones

Antes de usar nuevas funcionalidades, ejecuta las migraciones disponibles:

1. **Acceso a Migraciones:** `ecommerce/admin/migraciones.php`
2. **Migraciones disponibles:**
   - ✅ `migrar_productos_v2.php` - Atributos, imágenes múltiples (ya incluida)
   - ✅ `migrar_pedidos_atributos.php` - Almacenamiento de atributos en pedidos (ya incluida)
   - ⚠️ `migrar_atributo_opciones.php` - Opciones de atributos con imágenes (ejecutar si falla productos_atributos)

## Módulos

### 1. Categorías
- **Ruta:** `categorias.php`
- **Funciones:**
  - Listar todas las categorías
  - Crear nuevas categorías
  - Editar categorías existentes
  - Eliminar categorías
- **Campos:** nombre, descripción, icono, orden, estado

### 2. Productos
- **Ruta:** `productos.php`
- **Funciones:**
  - Listar todos los productos con filtro por categoría
  - Crear nuevos productos (con carga de imagen)
  - Editar productos existentes
  - Eliminar productos
  - Especificar tipo de precio (fijo o variable)
- **Campos:** código, nombre, descripción, categoría, precio base, tipo de precio, imagen, orden, estado
- **Formatos admitidos:** JPG, JPEG, PNG, GIF (máx 5MB)

### 3. Matriz de Precios
- **Ruta:** `matriz_precios.php?producto_id=ID`
- **Aplicable a:** Productos con tipo de precio = "variable" (cortinas, toldos)
- **Funciones:**
  - Ver matriz de precios existente
  - Generar matriz automáticamente
  - Agregar/editar entradas individuales
  - Eliminar entradas
- **Especificaciones:**
  - Alto: 10 cm a 300 cm (incrementos de 10cm)
  - Ancho: 10 cm a 300 cm (incrementos de 10cm)
  - Generación automática: 870 registros por producto

### 4. Información de la Empresa
- **Ruta:** `empresa.php`
- **Funciones:**
  - Editar información general de la empresa
  - Cargar/actualizar logo
  - Configurar datos de contacto
  - Definir horarios de atención
  - Agregar redes sociales
  - Establecer términos y condiciones
  - Definir política de privacidad
- **Campos:**
  - Básicos: nombre, descripción, logo
  - Contacto: email, teléfono
  - Ubicación: dirección, ciudad, provincia, país
  - Redes: Facebook, Instagram, WhatsApp
  - Legal: Términos y condiciones, Política de privacidad

### 5. Pedidos
- **Ruta:** `pedidos.php`
- **Funciones:**
  - Listar todos los pedidos
  - Filtrar por estado y fecha
  - Ver detalles completos de cada pedido
  - Cambiar estado de pedido
  - Ver información del cliente
- **Estados disponibles:**
  - Pendiente
  - Confirmado
  - Preparando
  - Enviado
  - Entregado
  - Cancelado

## Seguridad
- Solo usuarios con `$_SESSION['rol'] === 'admin'` pueden acceder
- Las contraseñas de usuarios se gestionan en el sistema principal
- No requiere credenciales separadas para el admin del ecommerce

## Requisitos de Base de Datos

### Tablas principales:
1. `ecommerce_categorias` - Categorías de productos
2. `ecommerce_productos` - Catálogo de productos
3. `ecommerce_matriz_precios` - Precios variables por medidas
4. `ecommerce_empresa` - Información de la empresa
5. `ecommerce_clientes` - Datos de clientes
6. `ecommerce_pedidos` - Pedidos realizados
7. `ecommerce_pedido_items` - Items dentro de cada pedido

## Cargas de Archivos
- **Imágenes de productos:** Carpeta `/ecommerce/uploads/`
- **Logo de empresa:** Carpeta `/ecommerce/uploads/`
- **Formatos:** JPG, JPEG, PNG, GIF
- **Tamaño máximo:** 5MB

## Mejores Prácticas
- Completa todos los campos obligatorios (marcados con *)
- Usa códigos de producto únicos y descriptivos
- Ordena las categorías y productos para una mejor visualización
- Genera la matriz de precios automáticamente si es posible
- Revisa regularmente los pedidos nuevos
- Mantén los datos de la empresa actualizados

## Soporte Técnico
Para problemas de acceso o errores de base de datos, verifica:
1. Que el usuario tenga rol "admin" en el sistema principal
2. Que la carpeta `/ecommerce/uploads/` tenga permisos de escritura
3. Que todas las tablas de ecommerce estén creadas
4. Revisa los logs del servidor para más detalles
