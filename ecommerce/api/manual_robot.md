# Manual de APIs para Robot

## Endpoints disponibles

| Nombre                  | URL                                                              | Descripción                                           |
|-------------------------|------------------------------------------------------------------|-------------------------------------------------------|
| Ordenes de producción   | `api/ordenes_produccion.php`                          | Devuelve órdenes filtrables por estado o por pedido   |
| Stock faltante          | `api/stock_faltante.php`                              | Lista materiales/productos con stock bajo             |

> Las URLs se construyen sobre el dominio principal.  
> Ejemplo base: `https://tucuroller.com.arapi/...`

Ambos endpoints aceptan **peticiones GET** y responden JSON con
`{ success: true, ... }`.

---

## 1. Órdenes de producción

### Parámetros

- `estado` – (`pendiente`, `en_produccion`, `terminado`, `entregado`, `cancelado`)
- `pedido_id` – entero (opcional)

### Ejemplos

```bash
# todas las órdenes
curl 'https://tucuroller.com.arapi/ordenes_produccion.php'

# sólo las en producción
curl 'https://tucuroller.com.arapi/ordenes_produccion.php?estado=en_produccion'

# búsqueda por pedido
curl 'https://tucuroller.com.arapi/ordenes_produccion.php?pedido_id=123'
```

### Respuesta

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

En caso de error:

```json
{ "success": false, "message": "Error interno" }
```

---

## 2. Stock faltante

### Parámetros

- `tipo` – `materiales`, `productos`, `todos` (por defecto `todos`)
- `alerta` – `bajo_minimo`, `negativo`, `sin_stock`, `todos` (por defecto `bajo_minimo`)
- `buscar` – texto (opcional) que busca en el nombre

### Ejemplos

```bash
# materiales con stock bajo
curl 'https://tucuroller.com.arapi/stock_faltante.php?tipo=materiales&alerta=bajo_minimo'

# productos sin stock
curl 'https://tucuroller.com.arapi/stock_faltante.php?tipo=productos&alerta=sin_stock'

# filtrar por nombre conteniendo “tornillo”
curl 'https://tucuroller.com.arapi/stock_faltante.php?buscar=tornillo&alerta=negativo'
```

### Respuesta

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

La alerta se calcula según el mismo criterio que en el panel de inventario
(`normal`, `bajo_minimo`, `negativo`, `sin_stock`).

---

## 3. Informe de ventas por mes

Endpoint: `/ecommerce/api/ventas_mes.php`

Parámetro obligatorio:

- `mes` en formato `YYYY-MM`.

Ejemplo:
```bash
curl 'https://tucuroller.com.ar/ecommerce/api/ventas_mes.php?mes=2026-03'
```

Respuesta:
```json
{
  "success": true,
  "mes": "2026-03",
  "total_ventas": 450000.00,
  "falta_cobrar": 125000.00
}
```

`total_ventas` suma de todos los pedidos de ese mes, y `falta_cobrar` la porción
cuyos estados no sean `pagado`.

---

## 4. Sueldos pendientes por empleado

Endpoint: `/ecommerce/api/sueldos_faltantes.php`

Parámetro obligatorio:

- `nombre`: texto a buscar en el nombre del empleado.- `mes` – mes YYYY-MM para limitar resultados (por defecto, mes actual)
Ejemplo:
```bash
curl 'https://tucuroller.com.ar/ecommerce/api/sueldos_faltantes.php?nombre=Juan'
```

Respuesta:
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
    },
    …
  ]
}
```

Cada fila corresponde a un mes registrado y el campo `faltante` indica el
monto restante por pagar.

---

## Ejemplos de código para el robot

### Python

```python
import requests

BASE = "https://tucuroller.com.arapi"

def consultar_ordenes(estado=None, pedido_id=None):
    params = {}
    if estado: params['estado'] = estado
    if pedido_id: params['pedido_id'] = pedido_id
    return requests.get(f"{BASE}/ordenes_produccion.php", params=params).json()

def stock_faltante(tipo="todos", alerta="bajo_minimo", buscar=""):
    params = {'tipo':tipo,'alerta':alerta,'buscar':buscar}
    return requests.get(f"{BASE}/stock_faltante.php", params=params).json()

# uso
print(consultar_ordenes(estado="en_produccion"))
print(stock_faltante(tipo="materiales", alerta="negativo"))
```

### PowerShell

```powershell
$base = "https://tucuroller.com.arapi"

# órdenes
Invoke-RestMethod -Uri "$base/ordenes_produccion.php?estado=pendiente"

# stock
Invoke-RestMethod -Uri "$base/stock_faltante.php?tipo=productos&alerta=sin_stock"
```

---

## Seguridad (opcional)

Ambos endpoints son públicos. Para protegerlos podés:

1. exigir un encabezado `X-API-KEY` y comparar con una clave en `config.php`
2. o verificar la sesión y permisos con `$can_access('produccion')` / `('inventario')`

---

Con este documento el robot podrá consultar las órdenes de producción y el
stock faltante. Podes adjuntar este archivo `.md` a su manual o incorporarlo
donde necesites.