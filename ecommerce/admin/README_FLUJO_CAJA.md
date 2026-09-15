# Módulo de Flujo de Caja

## 📋 Descripción

Módulo completo para gestionar el flujo de caja de la empresa, permitiendo:
- Registrar ingresos (pagos de pedidos, cotizaciones, etc.)
- Registrar egresos (gastos, compras, pagos de sueldo)
- **Pagos parciales de sueldos** con registro de fecha de cada pago
- Visualizar saldos y movimientos
- Generar reportes detallados

## 📁 Archivos Creados

### Tablas de Base de Datos
- **setup_flujo_caja.php** - Script para crear las tablas necesarias
  - `flujo_caja` - Registro de todas las transacciones
  - `pagos_sueldos_parciales` - Historial de pagos parciales de sueldos
  - `flujo_caja_resumen` - Resumen mensual automático

### Páginas Principales

1. **flujo_caja.php** - Dashboard principal
   - Vista mensual de ingresos/egresos
   - Resumen por categoría
   - Lista de transacciones
   - Filtros por tipo y mes
   - Botones rápidos para nuevos registros

2. **flujo_caja_ingreso.php** - Registrar nuevo ingreso
   - Categorías: Pago Pedido, Pago Orden Producción, Cotización, Crédito, Otro
   - Asociación opcional a pedidos existentes
   - Campos de referencia y observaciones

3. **flujo_caja_egreso.php** - Registrar nuevos egresos
   - **Tres pestañas:**
     - 💵 **Gastos**: Registrar gastos generales
     - 👨‍💼 **Pago de Sueldos**: Pagos parciales de sueldos con fecha
     - 📦 **Compras**: Vincular a compras existentes
   
   - **Para Sueldos Especialmente:**
     - Selecciona empleado y mes
     - Registra cada pago con su fecha
     - Controla el pendiente automáticamente
     - Evita pagos que superen el sueldo base

4. **flujo_caja_editar.php** - Editar transacción existente

5. **flujo_caja_eliminar.php** - Eliminar transacción
   - Eliminación en cascada de pagos parciales asociados
   - Confirmación de seguridad

6. **flujo_caja_reportes.php** - Reportes detallados
   - Rango de fechas personalizable
   - Resumen por categoría
   - Acumulado diario
   - Opción de impresión

7. **pagos_sueldos_parciales.php** - Gestión de pagos de sueldos
   - Vista todas los pagos parciales
   - Filtro por empleado y mes
   - Resumen de progreso (% pagado)
   - Seguimiento de montos pendientes

## 🚀 Instalación

### 1. Crear las Tablas (Una sola vez)

Opción A - Directamente en navegador:
```
Accede a: http://tu-sistema.com/setup_flujo_caja.php
```

Opción B - Por terminal:
```bash
php setup_flujo_caja.php
```

### 2. Acceder al Módulo

```
http://tu-sistema.com/flujo_caja.php
```

## 💰 Cómo Usar

### Registrar un Ingreso
1. Click en "Nuevo Ingreso" (botón verde)
2. Selecciona fecha y categoría
3. Ingresa el monto
4. (Opcional) Asocia a un pedido existente
5. Guarda

### Registrar un Egreso - Gasto
1. Click en "Nuevo Egreso" → Pestaña "Gastos"
2. Selecciona o crea nuevo gasto
3. Ingresa la categoría y monto
4. Guarda

### Registrar Pago de Sueldo (Parcial)
1. Click en "Nuevo Egreso" → Pestaña "Pago de Sueldos"
2. Selecciona el empleado
3. Selecciona el mes a pagar
4. Ingresa el monto **parcial** a pagar (no necesita ser el total)
5. Sistema muestra sueldo base y calcula automáticamente lo pendiente
6. Puedes hacer múltiples pagos para el mismo mes
7. El sistema controla que no superes el sueldo base

### Ejemplo de Pago Parcial
```
Empleado: Juan García
Sueldo Base: $100,000
Mes: Enero 2024

Pago 1 (01/01): $30,000 → Pendiente: $70,000
Pago 2 (15/01): $40,000 → Pendiente: $30,000
Pago 3 (31/01): $30,000 → Pendiente: $0,00 ✓ Completo

El sistema registra cada pago como transacción separada en flujo de caja
```

### Ver Historial de Pagos Parciales
1. Accede a "Pagos Parciales de Sueldos" (desde el menú lateral)
2. Filtra por empleado y/o mes
3. Ver progreso y detalles de cada pago

### Generar Reportes
1. Click en "Reportes"
2. Selecciona rango de fechas
3. Visualiza:
   - Ingresos/Egresos totales
   - Resumen por categoría
   - Acumulado diario
4. Imprime si lo necesitas

## 📊 Categorías Predefinidas

### Ingresos
- Pago Pedido
- Pago Orden Producción
- Cotización Aprobada
- Crédito
- Otro

### Egresos - Gastos
- Servicios
- Insumos
- Transporte
- Mantenimiento
- Utilidades
- Otro

### Egresos - Sueldos
- Automático según empleado

## 🔍 Características Especiales

### Control de Pagos Parciales
- ✅ Registra la fecha exacta de cada pago
- ✅ Suma automática del total pagado
- ✅ Calcula pendiente en tiempo real
- ✅ Impide pagos que superen el sueldo base
- ✅ Vista de progreso con porcentaje

### Validaciones
- ✅ No permite montos menores o iguales a 0
- ✅ Verifica que categoría sea seleccionada
- ✅ Controla montos en pagos de sueldo
- ✅ Registra usuario de cada transacción

### Información Automática
- ✅ Guarda fecha de creación/actualización
- ✅ Vincula con usuario logueado
- ✅ Asocia con pedidos/gastos/compras
- ✅ Calcula saldos automáticamente

## 📈 Reportes

El módulo proporciona:
- **Resumen Mensual**: Total de ingresos, egresos y saldo neto
- **Por Categoría**: Desglose detallado por tipo
- **Acumulado Diario**: Evolución del saldo día a día
- **Imprimible**: Cada reporte puede imprimirse directamente

## 🔒 Permisos

- Requiere estar logueado
- Registra usuario de cada transacción
- Ideal para auditoría y control interno

## 📝 Notas Importantes

1. **Pagos Parciales de Sueldo**: 
   - Ideal para empresas que pagan adelantos o cuotas
   - Cada pago tiene su propia fecha
   - Sistema calcula automáticamente lo pendiente

2. **Eliminación**:
   - Eliminar un pago de sueldo elimina automáticamente su registro en `pagos_sueldos_parciales`
   - La eliminación afecta los cálculos de pendiente

3. **Reportes**:
   - Se pueden exportar/imprimir directamente
   - Incluyen firma de timestamp

## 🛠️ Personalización Futura

Puedes extender fácilmente:
- Agregar más categorías de ingresos/egresos
- Integrar con métodos de pago (transferencia, efectivo, cheque)
- Crear presupuestos vs. real
- Agregar proyecciones de flujo
- Automatizar registros desde otros módulos

