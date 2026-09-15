# 🎉 Módulo Flujo de Caja - Resumen de Implementación

## ✅ Qué se ha creado

### 📊 Sistema Completo de Flujo de Caja con:

1. **Dashboard Principal** (`flujo_caja.php`)
   - Vista mensual de ingresos y egresos
   - Resumen de saldos
   - Tabla de transacciones
   - Filtros por tipo y mes

2. **Registros de Ingresos** (`flujo_caja_ingreso.php`)
   - Registro de pagos, créditos, etc.
   - Asociación a pedidos existentes
   - 5 categorías predefinidas

3. **Registros de Egresos** (`flujo_caja_egreso.php`) - CON 3 PESTAÑAS:
   - 💵 **Gastos generales** - Categorías: Servicios, Insumos, Transporte, etc.
   - 👨‍💼 **Pagos de Sueldos PARCIALES** ⭐ - **NOVEDAD**
   - 📦 **Compras** - Vinculación a compras existentes

4. **Sistema de Pagos Parciales de Sueldos** ⭐ (Característica Principal)
   ```
   Empleado: Juan García - Sueldo: $100,000
   
   01/01/2024 → Pago: $30,000  (Pendiente: $70,000)
   15/01/2024 → Pago: $40,000  (Pendiente: $30,000)
   31/01/2024 → Pago: $30,000  (Pendiente: $0 ✓)
   
   Cada pago se registra con su fecha exacta
   El sistema controla que no superes el sueldo base
   ```

5. **Gestión de Pagos Parciales** (`pagos_sueldos_parciales.php`)
   - Ver todos los pagos parciales de sueldos
   - Filtros por empleado y mes
   - Progreso visual (% pagado)
   - Seguimiento de pendientes

6. **Edición y Eliminación**
   - Editar transacciones existentes
   - Eliminar con confirmación
   - Eliminación en cascada de datos relacionados

7. **Reportes Detallados** (`flujo_caja_reportes.php`)
   - Rango de fechas personalizable
   - Resumen por categoría
   - Acumulado diario
   - Imprimible

8. **Importación de Datos** (`flujo_caja_importar.php`)
   - Sincroniza pagos de pedidos
   - Sincroniza gastos aprobados
   - Sincroniza compras pagadas
   - Sincroniza pagos de sueldos
   - Sin duplicados

### 📁 Archivos Creados (9 archivos PHP)

```
✓ setup_flujo_caja.php           - Crear tablas en BD
✓ flujo_caja.php                 - Dashboard principal
✓ flujo_caja_ingreso.php         - Registrar ingresos
✓ flujo_caja_egreso.php          - Registrar egresos (3 pestañas)
✓ flujo_caja_editar.php          - Editar transacciones
✓ flujo_caja_eliminar.php        - Eliminar transacciones
✓ flujo_caja_reportes.php        - Reportes y análisis
✓ pagos_sueldos_parciales.php    - Gestión de pagos parciales
✓ flujo_caja_importar.php        - Importar datos históricos
```

### 📚 Documentación Creada

```
✓ README_FLUJO_CAJA.md           - Guía completa de uso
✓ INTEGRACION_FLUJO_CAJA.md      - Integración con menú y otros módulos
```

### 🗄️ Tablas en Base de Datos (3 tablas)

```sql
CREATE TABLE flujo_caja (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fecha DATE,
    tipo ENUM('ingreso', 'egreso'),
    categoria VARCHAR(100),
    descripcion TEXT,
    monto DECIMAL(10,2),
    referencia VARCHAR(255),
    id_referencia INT,
    usuario_id INT,
    observaciones TEXT,
    fecha_creacion DATETIME,
    fecha_actualizacion DATETIME
);

CREATE TABLE pagos_sueldos_parciales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    empleado_id INT,
    mes_pago VARCHAR(7),
    sueldo_total DECIMAL(10,2),
    sueldo_pendiente DECIMAL(10,2),
    monto_pagado DECIMAL(10,2),
    fecha_pago DATE,
    usuario_registra INT,
    observaciones TEXT,
    fecha_creacion DATETIME
);

CREATE TABLE flujo_caja_resumen (
    id INT PRIMARY KEY AUTO_INCREMENT,
    año_mes VARCHAR(7),
    total_ingresos DECIMAL(10,2),
    total_egresos DECIMAL(10,2),
    saldo DECIMAL(10,2),
    fecha_actualizacion DATETIME
);
```

---

## 🚀 Cómo Empezar

### Paso 1: Crear las Tablas
Accede a: `http://tu-sistema.com/setup_flujo_caja.php`

O ejecuta por terminal:
```bash
php setup_flujo_caja.php
```

### Paso 2: Agregar al Menú
En `includes/header.php`, agrega:
```html
<li class="nav-item">
    <a class="nav-link" href="/flujo_caja.php">💰 Flujo de Caja</a>
</li>
```

### Paso 3: Importar Datos Históricos
Accede a: `http://tu-sistema.com/flujo_caja_importar.php`
- Selecciona qué deseas importar
- Click en "Importar"

### Paso 4: ¡Listo!
Accede al módulo: `http://tu-sistema.com/flujo_caja.php`

---

## 💡 Características Destacadas

### ⭐ Pagos Parciales de Sueldos
- Cada pago con su propia fecha
- Sistema automático de seguimiento
- Control de pendientes en tiempo real
- Historial completo en `pagos_sueldos_parciales.php`

### 📊 Visibilidad Completa
- Dashboard con resúmenes
- Ingresos vs Egresos claramente diferenciados
- Saldo neto calculado automáticamente
- Categorías por tipo

### 🔄 Sincronización de Datos
- Opción para importar de otros módulos
- Sin duplicados automáticos
- Cada transacción vinculada a su origen

### 📈 Reportes y Análisis
- Fecha inicial/final personalizable
- Desglose por categoría
- Acumulado diario
- Opción de impresión

### 🔒 Control y Auditoría
- Usuario de cada transacción registrado
- Fechas de creación/modificación
- Posibilidad de editar y eliminar
- Validaciones de montos

---

## 🎯 Casos de Uso

### 1️⃣ Registrar Pago de Pedido
```
1. Click "Nuevo Ingreso"
2. Categoría: "Pago Pedido"
3. Ingresa monto
4. Selecciona pedido (opcional)
5. Guardar
```
→ Aparece en dashboard como ingreso verde (+$)

### 2️⃣ Registrar Pago de Sueldo en 3 Cuotas
```
01/01: Click "Nuevo Egreso" → "Pago de Sueldos"
       Empleado: Juan, Mes: Enero 2024, Monto: $30,000
       
15/01: Mismo proceso, Monto: $40,000
       Sistema: Pendiente=$30,000
       
31/01: Mismo proceso, Monto: $30,000
       Sistema: Pendiente=$0 ✓ Completo
```
→ Se registran 3 transacciones separadas en flujo_caja
→ Todo se consolida en pagos_sueldos_parciales.php
→ Dashboard muestra total de egresos: $100,000

### 3️⃣ Ver Progreso de Pagos de Sueldo
```
1. Click "Pagos Parciales de Sueldos"
2. Filtra por empleado o mes
3. Ver barra de progreso
4. Detalle de cada pago con su fecha
```

### 4️⃣ Generar Reporte Mensual
```
1. Click "Reportes"
2. Selecciona fecha inicio/fin
3. Ver:
   - Total ingresos/egresos
   - Resumen por categoría
   - Evolución diaria
4. Imprimir si lo necesitas
```

---

## 🔗 Integración con Otros Módulos

### Ya Integrado:
- ✅ Pedidos (para asociar pagos)
- ✅ Empleados (para pagos de sueldo)
- ✅ Gastos (para filtrar aprobados)

### Puedes Integrar:
- Compras (si tienes tabla)
- Órdenes de producción
- Proveedores
- Métodos de pago

---

## 📋 Checklist de Instalación

- [ ] Ejecutar `setup_flujo_caja.php`
- [ ] Verificar que las 3 tablas fueron creadas
- [ ] Agregar menú en `header.php`
- [ ] Acceder a `flujo_caja.php`
- [ ] Importar datos históricos desde `flujo_caja_importar.php`
- [ ] Registrar primer ingreso
- [ ] Registrar primer egreso (gasto)
- [ ] Registrar primer pago de sueldo
- [ ] Generar reporte
- [ ] Ver pagos parciales

---

## ❓ Preguntas Frecuentes

**P: ¿Puedo registrar pagos parciales de cualquier cosa?**
R: El sistema está optimizado para sueldos, pero puedes registrar cualquier egreso. Los sueldos tienen controles especiales.

**P: ¿Qué pasa si elimino un pago parcial de sueldo?**
R: Se elimina de flujo_caja y se recalcula el pendiente automáticamente.

**P: ¿Puedo cambiar de mes un pago de sueldo?**
R: Sí, en flujo_caja_editar.php puedes editar la referencia.

**P: ¿Los reportes se pueden exportar?**
R: Sí, desde el navegador: Imprimir → Guardar como PDF

**P: ¿Dónde veo el saldo total actual?**
R: En el dashboard principal (flujo_caja.php) con color verde/rojo según sea positivo/negativo.

---

## 🎓 Notas para Administrador

1. **Pagos Parciales**: Ideal para adelantos, cuotas o disponibilidad de caja variable
2. **Auditoría**: Cada transacción registra quién la creó
3. **Integridad**: Las transacciones vinculadas a pedidos/empleados pueden rastrearse
4. **Análisis**: Los reportes muestran tendencias de flujo

---

## 📞 Soporte y Personalizaciones

Si necesitas:
- Agregar más categorías → Edita flujo_caja_egreso.php
- Cambiar colores → Modifica el CSS en los archivos
- Integrar método de pago → Agrega campo en tablas
- Automatizar importación → Crea script en cron
- Exportar a Excel → Agrega librería PHPExcel

---

**Módulo completamente funcional y listo para usar. ¡Que disfrutes! 🎉**

