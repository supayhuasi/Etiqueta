# Manual de APIs para Robot

## Base y autenticación

- Base URL sugerida: `https://tucuroller.com.ar/ecommerce/api`
- Formato: `application/json`
- Método principal: `GET` (consultas)
- Header para robot (sin sesión):

```http
X-API-KEY: <tu_clave_robot>
```

La clave se toma de `config.php` (`$robot_api_key`) o de la variable de entorno `GASTOS_API_KEY`.

---

## Endpoints disponibles

| Endpoint | Descripción |
|---|---|
| `/sistema.php` | API unificada para consultar módulos, buscar personas y escribir datos |
| `/ordenes_produccion.php` | Órdenes de producción con filtros por estado/pedido |
| `/stock_faltante.php` | Alertas de stock en materiales/productos |
| `/ventas_mes.php` | Totales de ventas y falta de cobro por mes |
| `/sueldos_faltantes.php` | Saldo pendiente por empleado y mes |
| `/consultar_precios.php` | Consulta de precios de productos (incluye matriz y descuentos) |

---

## 1) API unificada del sistema

Endpoint: `/ecommerce/api/sistema.php`

### Módulos soportados

- `usuarios`
- `empleados`
- `asistencias`
- `gastos`
- `cheques`
- `sueldos` (tabla `pagos_sueldos`)
- `pedidos`
- `ordenes_produccion`
- `produccion_items`
- `productos`
- `materiales`
- `clientes`

### Parámetros comunes

- `modulo` (obligatorio): módulo a consultar
- `id` (opcional): filtra por ID exacto (si la tabla tiene columna `id`)
- `q` (opcional): búsqueda textual en campos del módulo
- `mes` (opcional): formato `YYYY-MM` (si el módulo tiene campo de fecha)
- `desde` / `hasta` (opcional): rango de fechas `YYYY-MM-DD`
- `page` (opcional): página, default `1`
- `per_page` (opcional): cantidad por página, default `50`, máximo `200`
- `campos` (opcional): columnas a devolver separadas por coma
- filtros específicos por módulo (ej.: `empleado_id`, `estado`, `pedido_id`, etc.)

### Modo chat por persona (nombre/apellido)

Permite que el robot reciba texto natural (nombre o apellido) y encuentre la persona.

- `persona=1&q=<texto>`: busca coincidencias en empleados, usuarios y clientes.
- `persona=1&origen=<empleado|usuario|cliente>&persona_id=<id>`: trae perfil completo + relaciones.

Ejemplos:

```bash
# buscar por nombre o apellido
curl -H 'X-API-KEY: TU_API_KEY' \
  'https://tucuroller.com.ar/ecommerce/api/sistema.php?persona=1&q=rodriguez'

# obtener detalle completo de una coincidencia
curl -H 'X-API-KEY: TU_API_KEY' \
  'https://tucuroller.com.ar/ecommerce/api/sistema.php?persona=1&origen=empleado&persona_id=7'
```

Respuesta de búsqueda:

```json
{
  "success": true,
  "modo": "persona_busqueda",
  "q": "rodriguez",
  "total": 2,
  "personas": [
    {
      "origen": "empleado",
      "persona_id": 7,
      "nombre": "Juan Rodriguez",
      "email": "juan@empresa.com"
    }
  ]
}
```

Respuesta de detalle:

```json
{
  "success": true,
  "modo": "persona_detalle",
  "origen": "empleado",
  "persona_id": 7,
  "perfil": { "id": 7, "nombre": "Juan Rodriguez" },
  "relaciones": {
    "asistencias": [ ... ],
    "sueldos": [ ... ],
    "gastos": [ ... ]
  }
}
```

---

### Escritura desde robot (crear / actualizar)

Método: `POST` (también acepta `PUT`/`PATCH`) a `/ecommerce/api/sistema.php`.

JSON requerido:

```json
{
  "accion": "crear",
  "modulo": "gastos",
  "data": {
    "fecha": "2026-03-08",
    "descripcion": "Compra urgente",
    "monto": 25000
  }
}
```

Para actualizar:

```json
{
  "accion": "actualizar",
  "modulo": "cheques",
  "id": 10,
  "data": {
    "estado": "pagado"
  }
}
```

Módulos habilitados para escritura:

- `asistencias`
- `gastos`
- `cheques`
- `pedidos`
- `ordenes_produccion`
- `produccion_items`

Notas:
- sólo se guardan columnas válidas existentes en la tabla
- campos sensibles (`password`, `token`, etc.) quedan bloqueados

### Descubrir módulos y filtros

```bash
curl -H 'X-API-KEY: TU_API_KEY' \
  'https://tucuroller.com.ar/ecommerce/api/sistema.php?modulos=1'
```

Ejemplos:

```bash
# 1) Empleados activos
curl -H 'X-API-KEY: TU_API_KEY' \
  'https://tucuroller.com.ar/ecommerce/api/sistema.php?modulo=empleados&activo=1'

# 2) Gastos de un mes con paginación
curl -H 'X-API-KEY: TU_API_KEY' \
  'https://tucuroller.com.ar/ecommerce/api/sistema.php?modulo=gastos&mes=2026-03&page=1&per_page=100'

# 3) Cheques pendientes
curl -H 'X-API-KEY: TU_API_KEY' \
  'https://tucuroller.com.ar/ecommerce/api/sistema.php?modulo=cheques&estado=pendiente'

# 4) Pedido por ID
curl -H 'X-API-KEY: TU_API_KEY' \
  'https://tucuroller.com.ar/ecommerce/api/sistema.php?modulo=pedidos&id=123'

# 5) Productos, devolviendo sólo columnas puntuales
curl -H 'X-API-KEY: TU_API_KEY' \
  'https://tucuroller.com.ar/ecommerce/api/sistema.php?modulo=productos&campos=id,codigo,nombre,precio_base,activo'
```

### Respuesta estándar

```json
{
  "success": true,
  "modulo": "gastos",
  "tabla": "gastos",
  "filtros_aplicados": { "mes": "2026-03" },
  "paginacion": {
    "page": 1,
    "per_page": 50,
    "total": 124,
    "pages": 3
  },
  "items": [
    {
      "id": 1,
      "fecha": "2026-03-01",
      "descripcion": "Compra de materiales",
      "monto": 15000
    }
  ]
}
```

---

## 2) Órdenes de producción

Endpoint: `/ecommerce/api/ordenes_produccion.php`

Parámetros:
- `estado` (`pendiente`, `en_produccion`, `terminado`, `entregado`, `cancelado`)
- `pedido_id` (opcional)

Ejemplo:

```bash
curl 'https://tucuroller.com.ar/ecommerce/api/ordenes_produccion.php?estado=en_produccion'
```

---

## 3) Stock faltante

Endpoint: `/ecommerce/api/stock_faltante.php`

Parámetros:
- `tipo` = `materiales` | `productos` | `todos`
- `alerta` = `bajo_minimo` | `negativo` | `sin_stock` | `todos`
- `buscar` (opcional)

Ejemplo:

```bash
curl 'https://tucuroller.com.ar/ecommerce/api/stock_faltante.php?tipo=materiales&alerta=bajo_minimo'
```

---

## 4) Ventas por mes

Endpoint: `/ecommerce/api/ventas_mes.php`

Método: `GET`

Parámetro obligatorio:
- `mes` en formato `YYYY-MM`

Ejemplo:


```bash
curl 'https://tucuroller.com.ar/ecommerce/api/ventas_mes.php?mes=2026-03'
```

---

## 5) Sueldos faltantes

Endpoint: `/ecommerce/api/sueldos_faltantes.php`

Parámetros:
- `nombre` (obligatorio, texto parcial del empleado)
- `mes` (opcional, `YYYY-MM`, default mes actual)

Ejemplo:


```bash
curl 'https://tucuroller.com.ar/ecommerce/api/sueldos_faltantes.php?nombre=Juan&mes=2026-03'
```

---

## 6) Consultar precios

Endpoint: `/ecommerce/api/consultar_precios.php`

Parámetros principales:
- `producto_id` o `codigo`
- `alto` y `ancho` para productos de precio variable
- `todos=1` para listar catálogo activo

Ejemplo:

```bash
curl -H 'X-API-KEY: TU_API_KEY' \
  'https://tucuroller.com.ar/ecommerce/api/consultar_precios.php?codigo=ETQ-001&alto=120&ancho=200'
```

---

## Ejemplo rápido para robot (Python)

```python
import requests

BASE = "https://tucuroller.com.ar/ecommerce/api"
HEADERS = {"X-API-KEY": "TU_API_KEY"}

def consultar(modulo, **params):
    params["modulo"] = modulo
    r = requests.get(f"{BASE}/sistema.php", params=params, headers=HEADERS, timeout=20)
    r.raise_for_status()
    return r.json()

print(consultar("gastos", mes="2026-03", page=1, per_page=20))
print(consultar("pedidos", estado="pendiente", q="PED"))

# chat por nombre/apellido
print(requests.get(
  f"{BASE}/sistema.php",
  params={"persona": 1, "q": "rodriguez"},
  headers=HEADERS,
  timeout=20
).json())

# escritura (crear gasto)
print(requests.post(
  f"{BASE}/sistema.php",
  headers=HEADERS,
  json={
    "accion": "crear",
    "modulo": "gastos",
    "data": {
      "fecha": "2026-03-08",
      "descripcion": "Compra caja chica",
      "monto": 15000
    }
  },
  timeout=20
).json())
```

---

## Códigos de error esperados

- `400` parámetros inválidos o faltantes
- `403` autenticación inválida
- `500` error interno

Siempre que sea posible, la respuesta sigue este formato:

```json
{ "success": false, "message": "..." }
```

---

## Integración OpenClaw

Si tu bot corre en OpenClaw, usá la guía lista en:

- [ecommerce/api/openclaw_setup.md](ecommerce/api/openclaw_setup.md)

Incluye:
- system prompt recomendado
- definición de herramientas HTTP (`buscar_persona`, `detalle_persona`, `consultar_sistema`, `guardar_sistema`)
- plantillas JSON copiables para crear tools directamente en OpenClaw
- flujo de desambiguación por nombre/apellido para chat normal