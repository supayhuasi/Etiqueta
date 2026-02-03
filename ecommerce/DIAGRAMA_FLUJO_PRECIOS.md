# 🎯 Diagrama del Flujo de Precios con Opciones

## Flujo General del Sistema

```
┌─────────────────────────────────────────────────────────────────────┐
│                    SISTEMA DE PRECIOS CON OPCIONES                 │
└─────────────────────────────────────────────────────────────────────┘

                            PASO 1: ADMIN
                            ============

    Admin Panel
         │
         ├─ Selecciona Producto
         │       │
         │       ├─ Crea Atributo (ej: "Accesorios")
         │       │       │
         │       │       └─ Tipo: "select"
         │       │
         │       └─ Agrega Opciones:
         │           ├─ Opción 1: "Arandela" → Costo: $50.00
         │           ├─ Opción 2: "UV" → Costo: $30.00
         │           └─ Opción 3: "Gratis" → Costo: $0.00
         │
    DB: ecommerce_atributo_opciones
         ├─ id: 1, nombre: "Arandela", costo_adicional: 50.00
         ├─ id: 2, nombre: "UV", costo_adicional: 30.00
         └─ id: 3, nombre: "Gratis", costo_adicional: 0.00


                        PASO 2: TIENDA (FRONTEND)
                        =======================

    Cliente accede a Producto
         │
         ├─ Ve precio base: $500.00
         │
         ├─ Ve opciones con badges:
         │   ┌─────────────────────┐
         │   │ Arandela            │
         │   │ [Imagen]     +$50.00│ ← Badge con costo
         │   └─────────────────────┘
         │
         ├─ Selecciona opción
         │   └─ JavaScript: actualizarPrecio()
         │       ├─ Obtiene costo de opción: $50.00
         │       ├─ Suma: $500 + $50 = $550
         │       └─ Muestra desglose:
         │           Precio: $500.00
         │           + Arandela: $50.00
         │           = $550.00
         │
         └─ Agrega al carrito
             └─ Sesión: [id, precio, atributos{costo}]


                        PASO 3: CARRITO
                        ==============

    Cliente ve carrito
         │
         ├─ Producto: Cortina $500.00
         │   ├─ + Arandela: $50.00
         │   ├─ + UV: $30.00
         │   └─ Subtotal: $580.00
         │
         ├─ Cantidad: 2
         │   └─ Total Línea: $1,160.00
         │
         └─ Resumen:
             ├─ Subtotal: $1,160.00
             ├─ Envío: $500.00
             └─ TOTAL: $1,660.00


                        PASO 4: CHECKOUT
                        ===============

    Validación en servidor:
         ├─ Valida atributos seleccionados
         ├─ Recalcula costos desde DB
         ├─ Verifica total
         └─ Crea orden
```

## Flujo de Cálculo de Precio

```
PRECIO FINAL = PRECIO BASE + COSTOS OPCIONES + DESCUENTOS

Ejemplo:
────────

Producto: Cortina
Precio base: $100.00

Si es tipo "variable":
├─ Medidas (150×220cm): +$400.00
└─ Subtotal: $500.00

Atributos seleccionados:
├─ Arandela (opción con costo): +$50.00
├─ Color Blanco (opción sin costo): +$0.00
└─ Protección UV: +$30.00

Descuentos:
├─ Lista de precios: -5%
└─ Total descuento: -$27.50

CÁLCULO:
────────
Base: $500.00
+ Arandela: $50.00
+ UV: $30.00
- Descuento: -$27.50
─────────────────
TOTAL: $552.50
```

## Estructura de Datos en Carrito

```php
$_SESSION['carrito'] = [
    'item_key_1' => [
        'id' => 1,
        'nombre' => 'Cortina Premium',
        'precio' => 500.00,              // Precio base
        'cantidad' => 1,
        'alto' => 150,
        'ancho' => 220,
        'atributos' => [
            [
                'id' => 1,               // ID del atributo
                'nombre' => 'Accesorios',// Nombre del atributo
                'valor' => 'Arandela',   // Opción seleccionada
                'costo_adicional' => 50.00  // ← PRECIO ESPECIAL
            ],
            [
                'id' => 2,
                'nombre' => 'Protección',
                'valor' => 'UV',
                'costo_adicional' => 30.00
            ]
        ]
    ]
];

// Cálculo del precio total del ítem:
$precioItem = 500.00 + 50.00 + 30.00 = 580.00
$total = 580.00 * 1 = 580.00
```

## Visualización en Diferentes Vistas

### Admin Panel

```
┌────────────────────────────────────────┐
│ Opciones de "Accesorios"               │
├────────────────────────────────────────┤
│ ┌──────────────────────────────────┐   │
│ │ Arandela        [Imagen]  +$50.00│   │ ← Badge destacado
│ │                                  │   │
│ │ [Editar] [Eliminar]             │   │
│ └──────────────────────────────────┘   │
│                                        │
│ ┌──────────────────────────────────┐   │
│ │ Gratis          [Imagen]    Gratis   │ ← Sin costo
│ │                                  │   │
│ │ [Editar] [Eliminar]             │   │
│ └──────────────────────────────────┘   │
└────────────────────────────────────────┘
```

### Tienda (Producto)

```
Selecciona Accesorios:

┌─────────────┐  ┌─────────────┐  ┌─────────────┐
│ Arandela    │  │ UV          │  │ Gratis      │
│             │  │             │  │             │
│ [Imagen]    │  │ [Imagen]    │  │ [Imagen]    │
│             │  │             │  │             │
│ Arandela    │  │ UV          │  │ Gratis      │
│        +$50 │  │        +$30 │  │        Gratis│
└─────────────┘  └─────────────┘  └─────────────┘
      ✓ Seleccionada

Precio: $500.00
+ Arandela: $50.00
+ UV: $30.00
= $580.00
```

### Carrito

```
┌────────────────────────────────────────────────────┐
│ Cortina                                            │
│ 150×220cm                                          │
│ Accesorios: Arandela +$50.00                       │
│ Protección: UV +$30.00                             │
│                                                    │
│ Precio: $500.00                                    │
│         +$50.00 (Arandela)                         │
│         +$30.00 (UV)                               │
│         $580.00 ← Precio Final                     │
│                                                    │
│ Cantidad: 1     Subtotal: $580.00                  │
└────────────────────────────────────────────────────┘
```

## Validación y Seguridad

```
Cliente Frontend          →        Servidor Backend
─────────────────              ──────────────────

1. JavaScript calcula           ✓ Servidor recalcula
   precio local
   
2. Envía atributos         →    ✓ Valida cada atributo
   seleccionados                   en DB
   
3. Servidor verifica        →    ✓ Obtiene costos reales
   costos de BD
   
4. Compara totales         →    ✓ Rechaza si no coinciden
```

## Flujo de Verificación

```
¿Cliente intenta modificar precio en JS?
    ↓
    NO
    ↓
Servidor recalcula desde DB
    ↓
¿Totales coinciden?
    ↓ SÍ
Crea orden
    ↓
¿NO coinciden?
    ↓
Rechaza orden
```

---

**Diagrama Actualizado:** 3 de Febrero, 2026
