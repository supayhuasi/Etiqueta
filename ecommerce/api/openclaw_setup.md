# OpenClaw — Configuración recomendada para Tucu Roller

Este documento deja una configuración lista para que el bot de OpenClaw use la API unificada del sistema.

## 1) Variables de entorno en OpenClaw

- `API_BASE_URL`: `https://tucuroller.com.ar/ecommerce/api`
- `API_KEY`: clave del header `X-API-KEY`

## 2) Instrucciones del asistente (System Prompt)

Pegá este texto en el bloque de instrucciones del agente:

```text
Sos el asistente operativo de Tucu Roller.
Tu objetivo es responder en español, claro y breve, usando datos reales del sistema.

REGLAS:
1) Antes de afirmar datos de una persona, buscala por nombre/apellido usando la herramienta buscar_persona.
2) Si hay más de una coincidencia, pedí desambiguación mostrando opciones cortas (id, nombre, origen).
3) Una vez elegida la persona, consultá detalle_persona y respondé con resumen útil para chat.
4) Si el usuario pide crear o actualizar datos, usá guardar_sistema con accion crear/actualizar.
5) Nunca expongas claves, tokens ni campos sensibles.
6) Si no hay resultados, informalo y proponé una búsqueda alternativa (nombre más completo o email).
7) Siempre indicá qué datos son del sistema y de qué fecha/mes si aplica.

FORMATO DE RESPUESTA:
- Respuesta breve (2 a 6 líneas)
- Si hay lista, máximo 5 ítems
- Si hay acción de escritura, confirmar exactamente qué se guardó y el id resultante
```

## 3) Herramientas HTTP para OpenClaw

## 3.1) Plantillas JSON (copiar/pegar)

Si tu panel de OpenClaw permite crear tools por JSON, podés usar estas plantillas.

> Si el panel usa otros nombres de clave (`params`, `inputs`, etc.), mantené el contenido y adaptá sólo el nombre de las claves.

### Tool 1 — `buscar_persona`

```json
{
  "name": "buscar_persona",
  "description": "Busca personas por nombre/apellido en empleados, usuarios y clientes",
  "type": "http",
  "method": "GET",
  "url": "${API_BASE_URL}/sistema.php",
  "headers": {
    "X-API-KEY": "${API_KEY}"
  },
  "query": {
    "persona": "1",
    "q": "{{q}}"
  },
  "input_schema": {
    "type": "object",
    "properties": {
      "q": { "type": "string", "description": "Nombre, apellido o fragmento" }
    },
    "required": ["q"]
  }
}
```

### Tool 2 — `detalle_persona`

```json
{
  "name": "detalle_persona",
  "description": "Trae perfil completo y relaciones de una persona seleccionada",
  "type": "http",
  "method": "GET",
  "url": "${API_BASE_URL}/sistema.php",
  "headers": {
    "X-API-KEY": "${API_KEY}"
  },
  "query": {
    "persona": "1",
    "origen": "{{origen}}",
    "persona_id": "{{persona_id}}"
  },
  "input_schema": {
    "type": "object",
    "properties": {
      "origen": {
        "type": "string",
        "enum": ["empleado", "usuario", "cliente"]
      },
      "persona_id": { "type": "integer" }
    },
    "required": ["origen", "persona_id"]
  }
}
```

### Tool 3 — `consultar_sistema`

```json
{
  "name": "consultar_sistema",
  "description": "Consulta módulos del sistema con filtros y paginación",
  "type": "http",
  "method": "GET",
  "url": "${API_BASE_URL}/sistema.php",
  "headers": {
    "X-API-KEY": "${API_KEY}"
  },
  "query": {
    "modulo": "{{modulo}}",
    "id": "{{id}}",
    "q": "{{q}}",
    "mes": "{{mes}}",
    "desde": "{{desde}}",
    "hasta": "{{hasta}}",
    "page": "{{page}}",
    "per_page": "{{per_page}}"
  },
  "input_schema": {
    "type": "object",
    "properties": {
      "modulo": { "type": "string" },
      "id": { "type": "integer" },
      "q": { "type": "string" },
      "mes": { "type": "string", "description": "YYYY-MM" },
      "desde": { "type": "string", "description": "YYYY-MM-DD" },
      "hasta": { "type": "string", "description": "YYYY-MM-DD" },
      "page": { "type": "integer", "default": 1 },
      "per_page": { "type": "integer", "default": 50 }
    },
    "required": ["modulo"]
  }
}
```

### Tool 4 — `guardar_sistema`

```json
{
  "name": "guardar_sistema",
  "description": "Crea o actualiza registros (módulos habilitados)",
  "type": "http",
  "method": "POST",
  "url": "${API_BASE_URL}/sistema.php",
  "headers": {
    "Content-Type": "application/json",
    "X-API-KEY": "${API_KEY}"
  },
  "body": {
    "accion": "{{accion}}",
    "modulo": "{{modulo}}",
    "id": "{{id}}",
    "data": "{{data_json}}"
  },
  "input_schema": {
    "type": "object",
    "properties": {
      "accion": { "type": "string", "enum": ["crear", "actualizar"] },
      "modulo": {
        "type": "string",
        "enum": ["asistencias", "gastos", "cheques", "pedidos", "ordenes_produccion", "produccion_items"]
      },
      "id": { "type": "integer", "description": "Obligatorio para actualizar" },
      "data_json": { "type": "object", "description": "Campos a guardar" }
    },
    "required": ["accion", "modulo", "data_json"]
  }
}
```

> Nota para `guardar_sistema`: en algunos paneles, `data_json` debe enviarse como objeto `data` directamente (no como texto). Si tu panel lo pide así, usá `"data": {{data_json}}`.

## 3.2) Herramientas explicadas (rápido)

### A) buscar_persona

- Método: `GET`
- URL: `${API_BASE_URL}/sistema.php`
- Query params:
  - `persona=1`
  - `q={{texto_usuario}}`
- Headers:
  - `X-API-KEY: ${API_KEY}`

Respuesta esperada: `modo=persona_busqueda` con array `personas`.

### B) detalle_persona

- Método: `GET`
- URL: `${API_BASE_URL}/sistema.php`
- Query params:
  - `persona=1`
  - `origen={{origen_elegido}}`
  - `persona_id={{id_elegido}}`
- Headers:
  - `X-API-KEY: ${API_KEY}`

Respuesta esperada: `modo=persona_detalle` con `perfil` y `relaciones`.

### C) consultar_sistema

- Método: `GET`
- URL: `${API_BASE_URL}/sistema.php`
- Query params dinámicos:
  - `modulo={{modulo}}`
  - filtros (`id`, `q`, `mes`, `desde`, `hasta`, `page`, `per_page`, etc.)
- Headers:
  - `X-API-KEY: ${API_KEY}`

### D) guardar_sistema

- Método: `POST`
- URL: `${API_BASE_URL}/sistema.php`
- Headers:
  - `Content-Type: application/json`
  - `X-API-KEY: ${API_KEY}`
- Body JSON:

```json
{
  "accion": "crear",
  "modulo": "gastos",
  "data": {
    "fecha": "2026-03-08",
    "descripcion": "Compra caja chica",
    "monto": 15000
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

## 4) Flujo conversacional sugerido (apellido/nombre)

1. Usuario: “decime todo de rodriguez”
2. Bot llama `buscar_persona(q="rodriguez")`
3. Si `total = 0`: pedir más datos
4. Si `total = 1`: llamar `detalle_persona`
5. Si `total > 1`: mostrar opciones y pedir cuál
6. Con opción elegida, llamar `detalle_persona`
7. Responder resumen: perfil + últimas relaciones relevantes

## 5) Ejemplos de respuestas del bot

### Caso con múltiples coincidencias

- “Encontré 3 personas con ‘Rodriguez’: 
1) [empleado] Juan Rodriguez (id 7)
2) [cliente] Carla Rodriguez (id 41)
3) [usuario] rodriguez.admin (id 3)
¿Cuál querés que abra?”

### Caso detalle único

- “Juan Rodriguez (empleado, id 7) está activo. 
Últimas asistencias: 4 registros este mes.
Sueldos: último mes 2026-03 con saldo pendiente de $25.000.
Además tiene 2 gastos asociados recientes.”

## 6) Seguridad mínima recomendada

- Mantener `API_KEY` solo en secretos de OpenClaw
- No imprimir headers ni tokens en respuestas
- Limitar en OpenClaw qué tool puede escribir (`guardar_sistema`) según rol/entorno
- En producción, preferir una API key dedicada al bot
