# 📋 Manual de uso — API Consultar Precios

Endpoint: `/ecommerce/api/consultar_precios.php`

---

## Índice

1. [Autenticación](#1-autenticación)
2. [Modos de consulta](#2-modos-de-consulta)
   - 2.1 [Producto por ID](#21-producto-por-id)
   - 2.2 [Producto por código](#22-producto-por-código)
   - 2.3 [Listar todos los productos](#23-listar-todos-los-productos)
3. [Parámetros opcionales de medidas](#3-parámetros-opcionales-de-medidas)
4. [Respuestas](#4-respuestas)
   - 4.1 [Producto único](#41-producto-único)
   - 4.2 [Lista de productos](#42-lista-de-productos)
   - 4.3 [Errores](#43-errores)
5. [Ejemplos prácticos](#5-ejemplos-prácticos)
   - 5.1 [cURL](#51-curl)
   - 5.2 [PHP](#52-php)
   - 5.3 [JavaScript (fetch)](#53-javascript-fetch)
   - 5.4 [Python](#54-python)
6. [Tabla de códigos HTTP](#6-tabla-de-códigos-http)
7. [Preguntas frecuentes](#7-preguntas-frecuentes)

---

## 1. Autenticación

La API acepta **dos formas de autenticación**; basta con cumplir una de las dos:

| Método | Cómo usarlo |
|--------|-------------|
| **Sesión PHP activa** | Si la petición ya tiene una sesión de usuario iniciada (uso interno desde el panel), la API la acepta sin cabecera adicional. |
| **Header `X-API-KEY`** | Enviá la clave API configurada en el servidor como cabecera HTTP. |

### Configuración de la clave API

La clave se define en `config.php` o como variable de entorno:

```bash
# Variable de entorno (recomendado en producción)
GASTOS_API_KEY=mi_clave_secreta
```

Si no se define la variable de entorno, el valor por defecto configurado en `config.php` es el que se utiliza.

### Ejemplo de cabecera

```
X-API-KEY: mi_clave_secreta
```

> ⚠️ Si la clave es incorrecta y no hay sesión activa, la API devuelve `403 Forbidden`.

---

## 2. Modos de consulta

### 2.1 Producto por ID

Devuelve el precio de un producto específico buscándolo por su `id` en la base de datos.

```
GET /ecommerce/api/consultar_precios.php?producto_id=5
```

**Parámetro obligatorio:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `producto_id` | entero | ID del producto en `ecommerce_productos` |

---

### 2.2 Producto por código

Devuelve el precio buscando el producto por su campo `codigo`.

```
GET /ecommerce/api/consultar_precios.php?codigo=ETQ-001
```

**Parámetro obligatorio:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `codigo` | string | Código único del producto |

---

### 2.3 Listar todos los productos

Devuelve todos los productos activos con sus precios finales (aplicando la lista de precios pública).

```
GET /ecommerce/api/consultar_precios.php?todos=1
```

**Parámetro obligatorio:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `todos` | `1` | Activa el modo listado completo |

> No requiere `producto_id`, `codigo`, ni medidas.

---

## 3. Parámetros opcionales de medidas

Para productos con `tipo_precio = "variable"` (ej.: cortinas, toldos), el precio depende de las medidas. En ese caso `alto` y `ancho` son **obligatorios**; para productos de precio fijo se ignoran.

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `alto` | entero (cm) | Alto del producto en centímetros |
| `ancho` | entero (cm) | Ancho del producto en centímetros |

La API busca la combinación más cercana en la matriz de precios cargada en `ecommerce_matriz_precios`.

```
GET /ecommerce/api/consultar_precios.php?producto_id=5&alto=120&ancho=200
GET /ecommerce/api/consultar_precios.php?codigo=ETQ-001&alto=120&ancho=200
```

---

## 4. Respuestas

Todas las respuestas tienen `Content-Type: application/json; charset=utf-8`.

### 4.1 Producto único

```json
{
  "producto_id": 5,
  "codigo": "ETQ-001",
  "nombre": "Cortina Roller Blackout",
  "categoria_id": 2,
  "categoria": "Cortinas",
  "tipo_precio": "variable",
  "precio_base": 1500.00,
  "precio_final": 1275.00,
  "descuento_pct": 15.0,
  "moneda": "ARS",
  "alto": 120,
  "ancho": 200,
  "medidas_usadas": {
    "alto_cm": 120,
    "ancho_cm": 200
  }
}
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `producto_id` | entero | ID del producto |
| `codigo` | string | Código del producto |
| `nombre` | string | Nombre del producto |
| `categoria_id` | entero \| null | ID de la categoría |
| `categoria` | string \| null | Nombre de la categoría |
| `tipo_precio` | `"fijo"` \| `"variable"` | Tipo de precio del producto |
| `precio_base` | decimal | Precio antes de aplicar descuentos (o precio de la matriz para variables) |
| `precio_final` | decimal | Precio final al público (con descuentos de lista de precios aplicados) |
| `descuento_pct` | decimal | Porcentaje de descuento aplicado (0 si no hay) |
| `moneda` | string | Moneda (`"ARS"`) |
| `alto` | entero \| null | Alto solicitado en cm (null si no se envió) |
| `ancho` | entero \| null | Ancho solicitado en cm (null si no se envió) |
| `medidas_usadas` | objeto \| null | Medidas reales encontradas en la matriz (puede diferir ligeramente de las solicitadas) |

---

### 4.2 Lista de productos

```json
{
  "productos": [
    {
      "producto_id": 1,
      "codigo": "ETQ-001",
      "nombre": "Cortina Roller Blackout",
      "categoria_id": 2,
      "categoria": "Cortinas",
      "tipo_precio": "variable",
      "precio_base": 1500.00,
      "precio_final": 1275.00,
      "descuento_pct": 15.0,
      "moneda": "ARS"
    },
    {
      "producto_id": 2,
      "codigo": "ACC-010",
      "nombre": "Soporte de Pared",
      "categoria_id": 5,
      "categoria": "Accesorios",
      "tipo_precio": "fijo",
      "precio_base": 350.00,
      "precio_final": 350.00,
      "descuento_pct": 0.0,
      "moneda": "ARS"
    }
  ],
  "total": 2
}
```

> En el modo `todos=1`, los productos de tipo `"variable"` muestran el `precio_base` del producto (sin medidas específicas).

---

### 4.3 Errores

```json
{ "error": "Mensaje descriptivo del error" }
```

| Código HTTP | Significado |
|-------------|-------------|
| `400` | Parámetros inválidos o faltantes (ej.: falta `alto`/`ancho` en producto variable) |
| `403` | Autenticación fallida (clave incorrecta y sin sesión) |
| `404` | Producto no encontrado o sin precios para las medidas indicadas |
| `500` | Error interno del servidor |

---

## 5. Ejemplos prácticos

### 5.1 cURL

**Consultar producto por ID (precio fijo):**
```bash
curl -H "X-API-KEY: mi_clave_secreta" \
  "https://tudominio.com/ecommerce/api/consultar_precios.php?producto_id=2"
```

**Consultar producto variable con medidas:**
```bash
curl -H "X-API-KEY: mi_clave_secreta" \
  "https://tudominio.com/ecommerce/api/consultar_precios.php?producto_id=5&alto=120&ancho=200"
```

**Consultar por código:**
```bash
curl -H "X-API-KEY: mi_clave_secreta" \
  "https://tudominio.com/ecommerce/api/consultar_precios.php?codigo=ETQ-001&alto=120&ancho=200"
```

**Listar todos los productos:**
```bash
curl -H "X-API-KEY: mi_clave_secreta" \
  "https://tudominio.com/ecommerce/api/consultar_precios.php?todos=1"
```

---

### 5.2 PHP

```php
<?php
$apiKey  = 'mi_clave_secreta';
$baseUrl = 'https://tudominio.com/ecommerce/api/consultar_precios.php';

// Función auxiliar
function consultarPrecio(string $url, string $apiKey): array {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "X-API-KEY: {$apiKey}\r\n",
        ],
    ]);
    $json = file_get_contents($url, false, $ctx);
    return json_decode($json, true) ?? [];
}

// Producto por ID
$resultado = consultarPrecio("{$baseUrl}?producto_id=5&alto=120&ancho=200", $apiKey);
echo "Precio final: " . $resultado['precio_final'] . " " . $resultado['moneda'];

// Listar todos
$lista = consultarPrecio("{$baseUrl}?todos=1", $apiKey);
foreach ($lista['productos'] as $p) {
    echo "{$p['nombre']}: {$p['precio_final']} ARS\n";
}
```

---

### 5.3 JavaScript (fetch)

```javascript
const API_KEY = 'mi_clave_secreta';
const BASE_URL = 'https://tudominio.com/ecommerce/api/consultar_precios.php';

async function consultarPrecio(params) {
  const url = new URL(BASE_URL);
  Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));

  const res = await fetch(url, {
    headers: { 'X-API-KEY': API_KEY },
  });

  if (!res.ok) {
    const err = await res.json();
    throw new Error(`Error ${res.status}: ${err.error}`);
  }
  return res.json();
}

// Producto variable con medidas
consultarPrecio({ producto_id: 5, alto: 120, ancho: 200 })
  .then(data => console.log(`Precio final: ${data.precio_final} ${data.moneda}`))
  .catch(console.error);

// Todos los productos
consultarPrecio({ todos: 1 })
  .then(data => {
    console.log(`Total: ${data.total} productos`);
    data.productos.forEach(p => console.log(`${p.nombre}: $${p.precio_final}`));
  });
```

---

### 5.4 Python

```python
import requests

API_KEY  = 'mi_clave_secreta'
BASE_URL = 'https://tudominio.com/ecommerce/api/consultar_precios.php'
HEADERS  = {'X-API-KEY': API_KEY}

# Producto por código con medidas
resp = requests.get(BASE_URL, headers=HEADERS, params={
    'codigo': 'ETQ-001',
    'alto': 120,
    'ancho': 200,
})
resp.raise_for_status()
data = resp.json()
print(f"Precio final: {data['precio_final']} {data['moneda']}")
print(f"Descuento aplicado: {data['descuento_pct']}%")

# Listar todos los productos
resp = requests.get(BASE_URL, headers=HEADERS, params={'todos': 1})
resp.raise_for_status()
lista = resp.json()
print(f"\nTotal de productos: {lista['total']}")
for p in lista['productos']:
    print(f"  [{p['codigo']}] {p['nombre']}: ${p['precio_final']:.2f}")
```

---

## 6. Tabla de códigos HTTP

| Código | Texto | Cuándo ocurre |
|--------|-------|---------------|
| `200` | OK | Consulta exitosa |
| `400` | Bad Request | Falta `producto_id`/`codigo`, o falta `alto`/`ancho` para un producto variable |
| `403` | Forbidden | `X-API-KEY` incorrecto y no hay sesión activa |
| `404` | Not Found | El producto no existe, está inactivo, o no hay entrada en la matriz para esas medidas |
| `500` | Internal Server Error | Error inesperado en el servidor |

---

## 7. Preguntas frecuentes

**¿Por qué el `precio_base` devuelto puede ser diferente al que tiene el producto en la base de datos?**
Para productos de precio variable, `precio_base` corresponde al precio encontrado en la tabla `ecommerce_matriz_precios` para las medidas más cercanas a las solicitadas, no al `precio_base` del producto en sí.

**¿Qué pasa si pido `alto=115` pero en la matriz solo hay `alto=120`?**
La API busca la combinación más cercana (mínima diferencia `|alto_cm - alto| + |ancho_cm - ancho|`). El campo `medidas_usadas` en la respuesta indica cuáles medidas de la matriz se utilizaron efectivamente.

**¿Cómo sé qué descuento se aplica?**
El campo `descuento_pct` indica el porcentaje de descuento aplicado por la lista de precios pública. Si es `0`, no se aplicó ningún descuento.

**¿Los precios incluyen IVA?**
Depende de la configuración de la lista de precios. Los valores devueltos son los configurados en el sistema.

**¿Puedo usar la API sin `X-API-KEY` desde el navegador?**
Sí, si el usuario tiene una sesión PHP activa (por ejemplo, está logueado en el panel de administración), la API lo reconoce y no requiere la cabecera.

**¿Cómo cambio la clave API?**
Definí la variable de entorno `GASTOS_API_KEY` en el servidor, o editá el valor en `config.php`:
```php
$robot_api_key = 'nueva_clave_secreta';
```

---

> 📌 **Archivos relacionados:**
> - `ecommerce/api/consultar_precios.php` — endpoint principal documentado en este manual
> - `ecommerce/api/precio_producto.php` — endpoint simplificado (sin autenticación ni lista de precios)
> - `ecommerce/includes/precios_publico.php` — lógica de cálculo de precios y descuentos
