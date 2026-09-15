# 📊 FLUJO DE CAJA - DIAGRAMA DE ARQUITECTURA

## 🏗️ Estructura General del Sistema

```
┌─────────────────────────────────────────────────────────────────┐
│                    MÓDULO FLUJO DE CAJA                         │
│                                                                 │
│  ┌───────────────────────────────────────────────────────────┐ │
│  │            DASHBOARD PRINCIPAL (flujo_caja.php)           │ │
│  │                                                           │ │
│  │  ┌─────────────────┐  ┌─────────────────┐               │ │
│  │  │   INGRESOS      │  │    EGRESOS      │  SALDO NETO   │ │
│  │  │   $150,000      │  │   $100,000      │  $50,000  ✓   │ │
│  │  └─────────────────┘  └─────────────────┘               │ │
│  │                                                           │ │
│  │  Tabla: Todas las transacciones del mes                 │ │
│  └───────────────────────────────────────────────────────────┘ │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## 🔀 Flujo de Datos

```
┌──────────────────────────────────────────────────────────────────┐
│                                                                  │
│  OTROS MÓDULOS                    FLUJO DE CAJA                │
│  ──────────────                   ──────────────                │
│                                                                  │
│  📦 Pedidos        ──┐                 ┌──→  flujo_caja         │
│  (monto_pagado)      │                 │                        │
│                      ├──→ Ingresos ──→ │ (tipo = 'ingreso')    │
│  💰 Cotizaciones  ──┘                 │                        │
│                                       └──→  Base de Datos      │
│                                                                  │
│  💸 Gastos         ──┐                 ┌──→  flujo_caja         │
│                      ├──→ Egresos  ──→ │                        │
│  🛍️  Compras      ──┤                 │ (tipo = 'egreso')    │
│                      │                 │                        │
│  👨‍💼 Sueldos      ──┘                 └──→  Base de Datos      │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

## 🎯 Rutas y Funcionalidades

```
┌─────────────────────────────────────────────────────────────┐
│                     NAVEGACIÓN DEL SISTEMA                  │
└─────────────────────────────────────────────────────────────┘

                    flujo_caja.php
                    (INICIO)
                         │
        ┌────────────────┼────────────────┐
        │                │                │
        ▼                ▼                ▼
    INGRESOS         REPORTES          EGRESOS
        │                │                │
        │            flujo_caja_      flujo_caja_
        │           reportes.php      egreso.php
        │                │                │
        │                │         ┌──────┼──────┐
        │                │         │      │      │
        ▼                ▼         ▼      ▼      ▼
  flujo_caja_      Análisis    Gastos Sueldos Compras
  ingreso.php      Detallado


De cualquier página:
        │
        ▼
  flujo_caja_editar.php  (Editar)
        │
        ▼
  flujo_caja_eliminar.php (Eliminar)


Menú adicional:
        │
        ▼
  pagos_sueldos_parciales.php (Ver historia de pagos)
```

## 💾 Esquema de Base de Datos

```
┌─────────────────────────────────────────────────────────────┐
│                    flujo_caja (Tabla Principal)             │
├─────────────────────────────────────────────────────────────┤
│ id                    INT                                    │
│ fecha                 DATE                                   │
│ tipo                  ENUM('ingreso', 'egreso')              │
│ categoria             VARCHAR(100)                           │
│ descripcion           TEXT                                   │
│ monto                 DECIMAL(10,2)                          │
│ referencia            VARCHAR(255)                           │
│ id_referencia         INT                                    │
│ usuario_id            INT                                    │
│ observaciones         TEXT                                   │
│ fecha_creacion        DATETIME                               │
│ fecha_actualizacion   DATETIME                               │
└─────────────────────────────────────────────────────────────┘

        ↓  (Vinculado a)     ↓  (Vinculado a)

┌───────────────────────┐  ┌───────────────────────┐
│ pagos_sueldos_        │  │ flujo_caja_           │
│ parciales             │  │ resumen               │
├───────────────────────┤  ├───────────────────────┤
│ id                    │  │ id                    │
│ empleado_id           │  │ año_mes               │
│ mes_pago              │  │ total_ingresos        │
│ sueldo_total          │  │ total_egresos         │
│ sueldo_pendiente      │  │ saldo                 │
│ monto_pagado          │  │ fecha_actualizacion   │
│ fecha_pago            │  └───────────────────────┘
│ usuario_registra      │
│ observaciones         │
│ fecha_creacion        │
└───────────────────────┘
```

## 📊 Flujo de Pago Parcial de Sueldo (Detallado)

```
┌─────────────────────────────────────────────────────────────┐
│    EMPLEADO: Juan García - Sueldo: $100,000 - Enero 2024    │
└─────────────────────────────────────────────────────────────┘

                    DÍA 1: 01/01/2024
                    │
                    ▼
            ┌──────────────────┐
            │ Nuevo Egreso     │
            │ Tipo: Sueldo     │
            │ Monto: $30,000   │
            └────────┬─────────┘
                     │
         ┌───────────┴───────────┐
         │                       │
         ▼                       ▼
    ┌────────────────┐   ┌────────────────────────┐
    │  flujo_caja    │   │ pagos_sueldos_         │
    │                │   │ parciales              │
    │ fecha: 01/01   │   │                        │
    │ monto: 30000   │   │ monto_pagado: $30,000  │
    │ tipo: egreso   │   │ sueldo_pendiente:      │
    └────────────────┘   │   $70,000              │
                         └────────────────────────┘

                    DÍA 15: 15/01/2024
                    │
                    ▼
            ┌──────────────────┐
            │ Nuevo Egreso     │
            │ Tipo: Sueldo     │
            │ Monto: $40,000   │
            └────────┬─────────┘
                     │
         ┌───────────┴───────────┐
         │                       │
         ▼                       ▼
    ┌────────────────┐   ┌────────────────────────┐
    │  flujo_caja    │   │ pagos_sueldos_         │
    │                │   │ parciales              │
    │ fecha: 15/01   │   │                        │
    │ monto: 40000   │   │ monto_pagado: $70,000  │
    │ tipo: egreso   │   │ sueldo_pendiente:      │
    └────────────────┘   │   $30,000              │
                         └────────────────────────┘

                    DÍA 31: 31/01/2024
                    │
                    ▼
            ┌──────────────────┐
            │ Nuevo Egreso     │
            │ Tipo: Sueldo     │
            │ Monto: $30,000   │
            └────────┬─────────┘
                     │
         ┌───────────┴───────────┐
         │                       │
         ▼                       ▼
    ┌────────────────┐   ┌────────────────────────┐
    │  flujo_caja    │   │ pagos_sueldos_         │
    │                │   │ parciales              │
    │ fecha: 31/01   │   │                        │
    │ monto: 30000   │   │ monto_pagado: $100,000 │
    │ tipo: egreso   │   │ sueldo_pendiente:      │
    └────────────────┘   │   $0 ✓ COMPLETO       │
                         └────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│              RESULTADO FINAL EN FLUJO DE CAJA            │
│                                                          │
│  EGRESOS TOTALES MES: $100,000                          │
│  ─────────────────────────────────────────────────────  │
│  3 transacciones separadas:                             │
│    • 01/01 - $30,000                                    │
│    • 15/01 - $40,000                                    │
│    • 31/01 - $30,000                                    │
│  ─────────────────────────────────────────────────────  │
│  Cada una con su fecha exacta registrada                │
└──────────────────────────────────────────────────────────┘
```

## 🔄 Ciclo de Vida de una Transacción

```
┌─────────────┐
│   CREAR     │
│ Transacción │
│   Nueva     │
└──────┬──────┘
       │
       ▼
┌─────────────────────────────┐
│   Validar Datos             │
│  • Monto > 0                │
│  • Categoría seleccionada   │
│  • Fecha válida             │
│  • (Sueldo: no supera base) │
└──────┬──────────────────────┘
       │
       ├─→ ERROR? ─→ Mostrar mensaje
       │
       └─→ OK ✓
           │
           ▼
    ┌─────────────────┐
    │ Guardar en BD   │
    │ • flujo_caja    │
    │ • si es sueldo: │
    │   también en    │
    │   pagos_sueldos_│
    │   parciales     │
    └────────┬────────┘
             │
             ▼
    ┌─────────────────┐
    │   TRANSACCIÓN   │
    │   GUARDADA ✓    │
    │                 │
    │  Ahora puedes:  │
    │  • Verla en el  │
    │    dashboard    │
    │  • Editarla     │
    │  • Eliminarla   │
    │  • Reportarla   │
    └─────────────────┘
```

## 📈 Análisis y Reportes

```
┌──────────────────────────────────────────────────────────┐
│           REPORTE MENSUAL (flujo_caja_reportes.php)      │
└──────────────────────────────────────────────────────────┘

         RESUMEN TOTAL
         ─────────────────────────────
         Ingresos:  $150,000
         Egresos:   $100,000
         Saldo:     $50,000 ✓


         INGRESOS POR CATEGORÍA    |  EGRESOS POR CATEGORÍA
         ────────────────────────  │  ──────────────────────
         Pago Pedido: $100,000     │  Pago Sueldo: $80,000
         Pago Orden Prod: $30,000  │  Gastos: $15,000
         Otros: $20,000            │  Compras: $5,000


         ACUMULADO DIARIO
         ────────────────────────────
         01/01: +$100,000 = $100,000
         05/01: -$50,000  = $50,000
         10/01: +$75,000  = $125,000
         15/01: -$30,000  = $95,000
         20/01: -$20,000  = $75,000
         31/01: +$25,000  = $100,000
```

## 🔌 Integración con Otros Módulos

```
┌──────────────────────────────────────────────────────────┐
│              SINCRONIZACIÓN DE DATOS                     │
└──────────────────────────────────────────────────────────┘

    MÓDULO PEDIDOS          →    flujo_caja_importar.php
    ─────────────────               ↓
    Pagos registrados      →    Importar como INGRESO
                                  Categoría: "Pago Pedido"

    MÓDULO GASTOS          →    flujo_caja_importar.php
    ─────────────────               ↓
    Gastos aprobados       →    Importar como EGRESO
                                  Categoría: Tipo de gasto

    MÓDULO COMPRAS         →    flujo_caja_importar.php
    ─────────────────               ↓
    Compras pagadas        →    Importar como EGRESO
                                  Categoría: "Compra"

    MÓDULO SUELDOS         →    flujo_caja_importar.php
    ─────────────────               ↓
    Pagos registrados      →    Importar como EGRESO
                                  Categoría: "Pago Sueldo"
                           →    O usar sistema nuevo
                                  pagos_sueldos_parciales


    ✓ Sin duplicados automáticos
    ✓ Cada transacción vinculada a su origen
    ✓ Rastreable mediante id_referencia
```

## 🎯 Casos de Uso Visual

```
┌─────────────────────────────────────────────────────────┐
│  CASO 1: CONTROL DE FLUJO DIARIO                        │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Mañana: Revisar dashboard                             │
│  → ¿Qué ingresos llegaron hoy?                         │
│  → ¿Qué pagos debo hacer?                              │
│  → ¿Cuál es el saldo actual?                           │
│                                                         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  CASO 2: GESTIÓN DE PAGOS DE SUELDOS                   │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Mes: Enero 2024                                        │
│  Caja disponible: $150,000                              │
│  Nómina total: $200,000                                 │
│                                                         │
│  Solución:                                              │
│  01/01: Pago anticipado $80,000 (del flujo de 2024)   │
│  15/01: Pago del mes $90,000 (cobros del mes)         │
│  28/01: Pago final $30,000 (ajuste)                   │
│                                                         │
│  Visualizar en:                                         │
│  → pagos_sueldos_parciales.php                         │
│  → Ver progreso: 40% → 85% → 100%                      │
│                                                         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  CASO 3: ANÁLISIS MENSUAL                              │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Fin de mes: Generar reporte                           │
│  → Ingresos totales: $150,000                          │
│  → Egresos totales: $200,000                           │
│  → Saldo: -$50,000 (déficit)                           │
│  → Categoría con más gasto: Sueldos ($200,000)        │
│                                                         │
│  Decisiones:                                            │
│  → Reducir gastos en mes próximo?                      │
│  → Aumentar ingresos?                                   │
│  → Cobrar deudas pendientes?                           │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

**Diagrama Completo de Arquitectura del Módulo Flujo de Caja**
