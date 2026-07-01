# OpenSpec del proyecto

Este proyecto tiene dos piezas complementarias:

1. OpenSpec CLI oficial (Fission AI) para flujo de cambios/specs.
2. OpenAPI 3.0.3 autogenerado desde endpoints PHP actuales.

## OpenSpec CLI oficial

Instalacion global:

```bash
npm install -g @fission-ai/openspec@latest
```

Inicializacion del repo (ya aplicada en este proyecto):

```bash
openspec init . --tools github-copilot
```

Comandos utiles:

```bash
openspec list
openspec validate
openspec update .
```

Estructura creada por OpenSpec:

- `.github/prompts/`
- `.github/skills/`

## Archivos

- `openspec/generate_openapi.php`: generador automatico de la especificacion.
- `openspec/openapi.json`: especificacion OpenAPI generada.
- `openspec/index.html`: visor Swagger UI para navegar la API.

## Como actualizar la especificacion

### Opcion 1: con Composer

```bash
composer openspec:generate
```

### Opcion 2: directamente con PHP

```bash
php openspec/generate_openapi.php
```

## Modo seguimiento automatico (watch)

Regenera la spec cuando detecta cambios en APIs:

```bash
composer openspec:watch
```

O con intervalo personalizado (segundos):

```bash
php openspec/generate_openapi.php --watch --interval=3
```

## Que endpoints escanea

- `api/**/*.php`
- `ecommerce/api/**/*.php`
- `scan_api.php`

## Como ver la documentacion

Abrir en navegador:

- `openspec/index.html`

Swagger UI carga la spec desde `openspec/openapi.json`.

## Nota

La generacion es automatica por analisis estatico del codigo (heuristicas). Esto permite mantener la spec alineada con lo desarrollado sin documentacion manual endpoint por endpoint.
