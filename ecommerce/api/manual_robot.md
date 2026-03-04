# Manual de APIs para Robot

Este manual resume los endpoints que puede consumir el robot y deja ejemplos listos para usar.

## Base de URLs

- Dominio: `https://tucuroller.com.ar`
- Endpoints de ecommerce: `https://tucuroller.com.ar/ecommerce/api/...`
- Endpoint de gastos (admin): `https://tucuroller.com.ar/ecommerce/admin/gastos/gastos_api.php`

Formato general de respuesta:

```json
{ "success": true, "...": "..." }
```

En error:

```json
{ "success": false, "message": "Detalle del error" }
```

---

## 1) Órdenes de producción

Endpoint: `/ecommerce/api/ordenes_produccion.php`

Método: `GET`

Parámetros:

- `estado` (opcional): `pendiente`, `en_produccion`, `terminado`, `entregado`, `cancelado`
- `pedido_id` (opcional): entero

Ejemplos:

```bash
# todas las órdenes
curl 'https://tucuroller.com.ar/ecommerce/api/ordenes_produccion.php'

# sólo las en producción
curl 'https://tucuroller.com.ar/ecommerce/api/ordenes_produccion.php?estado=en_produccion'

# búsqueda por pedido
curl 'https://tucuroller.com.ar/ecommerce/api/ordenes_produccion.php?pedido_id=123'
```

Respuesta ejemplo:

```json
{
  "success": true,
  "ordenes": [
    {
      "id": 42,
      "pedido_id": 123,
      "estado": "en_produccion",
      "notas": "...",
      "fecha_entrega": "2026-03-10",
      "materiales_descontados": 0,
      "fecha_creacion": "2026-03-01 14:22:00",
      "fecha_actualizacion": "2026-03-01 15:00:00",
      "numero_pedido": "PED-000123"
    }
  ]
}
```

---

## 2) Stock faltante

Endpoint: `/ecommerce/api/stock_faltante.php`

Método: `GET`

Parámetros:

- `tipo` (opcional): `materiales`, `productos`, `todos` (default: `todos`)
- `alerta` (opcional): `bajo_minimo`, `negativo`, `sin_stock`, `todos` (default: `bajo_minimo`)
- `buscar` (opcional): texto a buscar en el nombre

Ejemplos:

```bash
# materiales con stock bajo
curl 'https://tucuroller.com.ar/ecommerce/api/stock_faltante.php?tipo=materiales&alerta=bajo_minimo'

# productos sin stock
curl 'https://tucuroller.com.ar/ecommerce/api/stock_faltante.php?tipo=productos&alerta=sin_stock'

# filtrar por nombre
curl 'https://tucuroller.com.ar/ecommerce/api/stock_faltante.php?buscar=tornillo&alerta=negativo'
```

Respuesta ejemplo:

```json
{
  "success": true,
  "items": [
    {
      "tipo": "material",
      "id": 17,
      "nombre": "Perfil aluminio",
      "stock": 2.00,
      "stock_minimo": 5.00,
      "unidad_medida": "kg",
      "tipo_origen": "compra"
    }
  ]
}
```

La alerta sigue el mismo criterio que inventario: `normal`, `bajo_minimo`, `negativo`, `sin_stock`.

---

## 3) Informe de ventas por mes

Endpoint: `/ecommerce/api/ventas_mes.php`

Método: `GET`

Parámetro obligatorio:

- `mes` en formato `YYYY-MM`

Ejemplo:

```bash
curl 'https://tucuroller.com.ar/ecommerce/api/ventas_mes.php?mes=2026-03'
```

Respuesta ejemplo:

```json
{
  "success": true,
  "mes": "2026-03",
  "total_ventas": 450000.00,
  "falta_cobrar": 125000.00
}
```

`total_ventas` es la suma de pedidos del mes. `falta_cobrar` corresponde a pedidos aún no pagados.

---

## 4) Sueldos pendientes por empleado

Endpoint: `/ecommerce/api/sueldos_faltantes.php`

Método: `GET`

Parámetros:

- `nombre` (obligatorio): texto a buscar en nombre del empleado
- `mes` (opcional): `YYYY-MM` (default: mes actual)

Ejemplo:

```bash
curl 'https://tucuroller.com.ar/ecommerce/api/sueldos_faltantes.php?nombre=Juan'
```

Respuesta ejemplo:

```json
{
  "success": true,
  "nombre": "Juan",
  "registros": [
    {
      "empleado_id": 3,
      "empleado_nombre": "Juan Pérez",
      "mes_pago": "2026-02",
      "sueldo_total": 50000.00,
      "monto_pagado": 25000.00,
      "fecha_pago": "2026-03-01 10:15:00",
      "faltante": 25000.00
    }
  ]
}
```

Cada fila representa un mes y `faltante` indica lo pendiente por pagar.

---

## 5) Crear gasto (API de gastos)

Endpoint: `/ecommerce/admin/gastos/gastos_api.php`

Método: `POST`

Headers recomendados:

- `Content-Type: application/json`
- `X-API-KEY: <tu_clave>`

Campos obligatorios:

- `fecha` (formato `YYYY-MM-DD`)
- `descripcion` (texto)
- `monto` (número > 0)
- tipo de gasto: `tipo_gasto_id` o alias (`tipo`, `tipo_id`, `tipo_gasto`)
- estado: `estado_gasto_id` o alias (`estado`, `estado_id`, `estado_gasto`)

Campos opcionales:

- `empleado_id`
- `observaciones`
- `archivo` (objeto con `filename` y `content` en base64)

Payload recomendado (OpenClaw):

```json
{
  "fecha": "2026-03-04",
  "tipo_gasto_id": 2,
  "estado_gasto_id": 1,
  "descripcion": "Compra de insumos para producción",
  "monto": 18500.50,
  "empleado_id": 3,
  "observaciones": "Registrado por robot"
}
```

Payload alternativo válido (por nombre):

```json
{
  "fecha": "2026-03-04",
  "tipo": "Insumos",
  "estado": "Pendiente",
  "descripcion": "Compra de insumos para producción",
  "monto": 18500.50
}
```

Ejemplo curl:

```bash
curl -X POST 'https://tucuroller.com.ar/ecommerce/admin/gastos/gastos_api.php' \
  -H 'Content-Type: application/json' \
  -H 'X-API-KEY: 3020450830204508' \
  -d '{
    "fecha":"2026-03-04",
    "tipo_gasto_id":2,
    "estado_gasto_id":1,
    "descripcion":"Compra de insumos",
    "monto":18500.50
  }'
```

Respuesta exitosa:

```json
{ "success": true, "gasto_id": 123 }
```

---

## 6) Tareas de producción por usuario

Endpoint: `/ecommerce/api/produccion_tareas_usuarios.php`

Método: `GET`

Autenticación:

- Sesión activa en el sistema, **o**
- Header `X-API-KEY: <tu_clave>`

Parámetros opcionales:

- `usuario_id` (entero): filtra por un usuario específico
- `solo_activos` (`0` o `1`): si es `1`, excluye usuarios cuya última etapa es `terminado`

Ejemplos:

```bash
# ver última tarea de todos los usuarios
curl 'https://tucuroller.com.ar/ecommerce/api/produccion_tareas_usuarios.php' \
  -H 'X-API-KEY: 3020450830204508'

# ver sólo un usuario
curl 'https://tucuroller.com.ar/ecommerce/api/produccion_tareas_usuarios.php?usuario_id=3' \
  -H 'X-API-KEY: 3020450830204508'

# ver sólo usuarios activos (no terminados)
curl 'https://tucuroller.com.ar/ecommerce/api/produccion_tareas_usuarios.php?solo_activos=1' \
  -H 'X-API-KEY: 3020450830204508'
```

Respuesta ejemplo:

```json
{
  "success": true,
  "total": 2,
  "usuarios": [
    {
      "usuario_id": 7,
      "usuario_nombre": "Operario 1",
      "etapa": "armado",
      "created_at": "2026-03-04 11:25:10",
      "estado_item": "armado",
      "numero_item": 2,
      "codigo_barcode": "OP000042-IT000311-002",
      "producto_nombre": "Cortina Roller Blackout",
      "pedido_id": 50,
      "numero_pedido": "PED-000050"
    }
  ]
}
```

`etapa` refleja la tarea registrada por escaneo: `corte`, `armado`, `terminado`.

---

## Ejemplos de código para el robot

### Python

```python
import requests

BASE = "https://tucuroller.com.ar/ecommerce/api"

def consultar_ordenes(estado=None, pedido_id=None):
    params = {}
    if estado:
        params["estado"] = estado
    if pedido_id:
        params["pedido_id"] = pedido_id
    return requests.get(f"{BASE}/ordenes_produccion.php", params=params, timeout=30).json()

def stock_faltante(tipo="todos", alerta="bajo_minimo", buscar=""):
    params = {"tipo": tipo, "alerta": alerta, "buscar": buscar}
    return requests.get(f"{BASE}/stock_faltante.php", params=params, timeout=30).json()

print(consultar_ordenes(estado="en_produccion"))
print(stock_faltante(tipo="materiales", alerta="negativo"))
```

### PowerShell

```powershell
$base = "https://tucuroller.com.ar/ecommerce/api"

# Órdenes
Invoke-RestMethod -Uri "$base/ordenes_produccion.php?estado=pendiente"

# Stock
Invoke-RestMethod -Uri "$base/stock_faltante.php?tipo=productos&alerta=sin_stock"
```

---

## Notas de seguridad

- Para endpoints sensibles, exigir `X-API-KEY`.
- Alternativamente, validar sesión/permisos en servidor.
- No exponer la clave en logs o repositorios.

---

Con este manual, el robot puede consultar producción/stock, obtener indicadores y crear gastos con payload consistente.