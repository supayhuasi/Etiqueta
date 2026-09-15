# 📊 MÓDULO FLUJO DE CAJA - INSTALACIÓN RÁPIDA

## 🎯 Resumen Ejecutivo

Se ha creado un **módulo completo de Flujo de Caja** con:
- ✅ Dashboard de ingresos/egresos
- ✅ **Pagos de sueldo en PARCIALES** (con fecha cada pago)
- ✅ Reportes detallados
- ✅ Importación de datos históricos
- ✅ 9 archivos PHP + 3 tablas BD

---

## ⚡ Instalación en 3 pasos

### 1. Crear Tablas (1 minuto)
```
http://tu-sistema.com/setup_flujo_caja.php
```

### 2. Agregar al Menú (1 minuto)
En `includes/header.php` agrega:
```html
<li><a href="/flujo_caja.php">💰 Flujo de Caja</a></li>
```

### 3. Importar Datos (2 minutos)
```
http://tu-sistema.com/flujo_caja_importar.php
```

**¡Listo!** Accede a `http://tu-sistema.com/flujo_caja.php`

---

## 📁 Archivos Creados (9)

| Archivo | Función |
|---------|---------|
| `setup_flujo_caja.php` | Crear tablas |
| `flujo_caja.php` | 📊 Dashboard principal |
| `flujo_caja_ingreso.php` | ➕ Registrar ingresos |
| `flujo_caja_egreso.php` | ➖ Registrar egresos (3 tipos) |
| `flujo_caja_editar.php` | ✏️ Editar transacciones |
| `flujo_caja_eliminar.php` | 🗑️ Eliminar transacciones |
| `flujo_caja_reportes.php` | 📈 Reportes y análisis |
| `pagos_sueldos_parciales.php` | 👨‍💼 Gestión pagos parciales |
| `flujo_caja_importar.php` | 📥 Importar datos históricos |

---

## 🗄️ Tablas en Base de Datos (3)

```
📋 flujo_caja                    → Todas las transacciones
👨‍💼 pagos_sueldos_parciales        → Pagos de sueldo en cuotas
📊 flujo_caja_resumen            → Resumen mensual
```

---

## 🎓 Cómo Usar

### 👨‍💼 Registrar Pago de Sueldo en 3 Cuotas

```
EMPLEADO: Juan García
SUELDO BASE: $100,000
MES: Enero 2024

01/01 → Click "Nuevo Egreso" → "Pago de Sueldos"
        Monto: $30,000 → Pendiente: $70,000

15/01 → Click "Nuevo Egreso" → "Pago de Sueldos"  
        Monto: $40,000 → Pendiente: $30,000

31/01 → Click "Nuevo Egreso" → "Pago de Sueldos"
        Monto: $30,000 → Pendiente: $0 ✓ COMPLETO

✨ RESULTADO:
   - 3 transacciones en flujo de caja
   - Cada una con su fecha exacta
   - Total: $100,000
   - Sistema calcula automáticamente lo pendiente
```

### 💵 Registrar Ingreso (Pago de Pedido)

```
Click "Nuevo Ingreso" 
→ Categoría: "Pago Pedido"
→ Ingresa monto
→ (Opcional) Selecciona pedido
→ Guardar
```

### 💸 Registrar Egreso (Gasto)

```
Click "Nuevo Egreso" → Pestaña "Gastos"
→ Categoría: Servicios/Insumos/Transporte/etc
→ Ingresa monto
→ Guardar
```

### 📈 Ver Reportes

```
Click "Reportes"
→ Selecciona fecha inicio/fin
→ Ver análisis completo
→ Imprimir si lo necesitas
```

---

## 🌟 Características Principales

### ⭐ Pagos Parciales de Sueldos
- Cada pago con **fecha exacta**
- **Control automático** de pendientes
- **Impide pagos** que superen sueldo base
- **Historial completo** visible

### 📊 Dashboard Completo
- Ingresos vs Egresos lado a lado
- **Saldo neto** automático
- **Resumen por categoría**
- **Tabla detallada** de transacciones

### 🔄 Sincronización de Datos
- Importar histórico de:
  - ✅ Pagos de pedidos
  - ✅ Gastos aprobados
  - ✅ Compras pagadas
  - ✅ Pagos de sueldos
- **Sin duplicados** automáticos

### 📈 Reportes Profesionales
- Rango de fechas personalizable
- Desglose por categoría
- Evolución diaria
- Opción de **impresión**

### 🔒 Control y Auditoría
- Usuario de cada transacción registrado
- Fechas de creación/modificación
- Editar/eliminar cuando sea necesario
- Validaciones automáticas

---

## 💡 Ejemplos de Uso

### Escenario 1: Adelantos de Sueldo
```
Empleado: María López - Sueldo: $80,000 - Mes: Marzo 2024

10/03: Adelanto $20,000 → Pendiente: $60,000
25/03: Adelanto $30,000 → Pendiente: $30,000
30/03: Pago final $30,000 → Completo ✓

Sistema muestra:
- Fecha de cada pago
- Total pagado vs pendiente
- Progreso visual
```

### Escenario 2: Pago Según Disponibilidad de Caja
```
Mes: Febrero 2024
Sueldo Base Total: $200,000

01/02: Disponible $50,000 → Pago $50,000
15/02: Disponible $100,000 → Pago $100,000  
28/02: Disponible $50,000 → Pago $50,000
Total: $200,000 ✓

Cada pago registrado con su fecha
Sistema controla que no haya duplicados
```

### Escenario 3: Reporte Mensual
```
Mes: Enero 2024

INGRESOS:
  Pago Pedidos: $50,000
  Otros: $5,000
  TOTAL: $55,000

EGRESOS:
  Sueldos: $100,000
  Gastos: $10,000
  Compras: $20,000
  TOTAL: $130,000

SALDO NETO: -$75,000

(Con gráficos y desglose por categoría)
```

---

## 📋 Checklist de Setup

```
☐ Paso 1: setup_flujo_caja.php (crear tablas)
☐ Paso 2: Agregar menú en header.php
☐ Paso 3: flujo_caja_importar.php (datos históricos)
☐ Paso 4: Probar acceso a flujo_caja.php
☐ Paso 5: Registrar primer ingreso
☐ Paso 6: Registrar primer pago de sueldo
☐ Paso 7: Verificar en pagos_sueldos_parciales.php
☐ Paso 8: Generar primer reporte
```

---

## 🔗 Menú Recomendado

```html
<!-- Opción 1: Simple -->
<li><a href="/flujo_caja.php">💰 Flujo de Caja</a></li>

<!-- Opción 2: Con Submenu -->
<li>
    <a href="#" onclick="toggle_submenu()">💰 Finanzas</a>
    <ul id="submenu_finanzas" style="display:none">
        <li><a href="/flujo_caja.php">📊 Flujo de Caja</a></li>
        <li><a href="/flujo_caja_ingreso.php">➕ Nuevo Ingreso</a></li>
        <li><a href="/flujo_caja_egreso.php">➖ Nuevo Egreso</a></li>
        <li><a href="/pagos_sueldos_parciales.php">👨‍💼 Pagos de Sueldos</a></li>
        <li><a href="/flujo_caja_reportes.php">📈 Reportes</a></li>
    </ul>
</li>
```

---

## 🎯 Categorías Incluidas

### Ingresos
- 💰 Pago Pedido
- 💰 Pago Orden Producción
- 💰 Cotización Aprobada
- 💰 Crédito
- 💰 Otro

### Egresos - Gastos
- 💸 Servicios
- 💸 Insumos
- 💸 Transporte
- 💸 Mantenimiento
- 💸 Utilidades
- 💸 Otro

### Egresos - Sueldos
- 👨‍💼 Automático (por empleado)

---

## ❓ FAQ Rápido

**P: ¿Necesito crear las tablas manualmente?**
R: No. Accede a `setup_flujo_caja.php` y se crean automáticamente.

**P: ¿Puedo registrar pagos parciales de cualquier cosa?**
R: El sistema está optimizado para sueldos, pero puedes registrar cualquier egreso.

**P: ¿Dónde veo el saldo actual?**
R: En el dashboard principal, con color verde (positivo) o rojo (negativo).

**P: ¿Puedo eliminar transacciones?**
R: Sí, con confirmación. Si es pago de sueldo, se recalcula automáticamente.

**P: ¿Se puede exportar a Excel?**
R: Los reportes se pueden imprimir como PDF. Para Excel, ver EJEMPLOS_FLUJO_CAJA_AVANZADO.php

---

## 📚 Documentación Incluida

| Documento | Contenido |
|-----------|----------|
| `README_FLUJO_CAJA.md` | Guía completa y casos de uso |
| `INTEGRACION_FLUJO_CAJA.md` | Cómo integrar con tu menú |
| `MODULO_FLUJO_CAJA_COMPLETO.md` | Resumen y características |
| `EJEMPLOS_FLUJO_CAJA_AVANZADO.php` | Código para personalizaciones |

---

## 🚀 Próximos Pasos (Opcional)

### Para Personalizar:
1. Ver `EJEMPLOS_FLUJO_CAJA_AVANZADO.php`
2. Adaptar categorías a tus necesidades
3. Agregar métodos de pago si lo necesitas

### Para Integración:
1. Automatizar pagos de pedidos
2. Integrar con Mercado Pago
3. Enviar reportes por email

### Para Análisis:
1. Crear presupuestos vs real
2. Proyectar flujo futuro
3. Generar alertas de saldo bajo

---

## 📞 Soporte

Si algo no funciona:
1. Verifica que las tablas se crearon: `setup_flujo_caja.php`
2. Verifica permisos de archivos
3. Revisa el archivo de errores del servidor
4. Consulta `README_FLUJO_CAJA.md` para más detalles

---

**✅ MÓDULO LISTO PARA USAR**

Accede ahora a: `http://tu-sistema.com/flujo_caja.php`

