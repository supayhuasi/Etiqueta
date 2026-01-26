# 📖 Guía de Uso: Módulo de Cheques Mejorado

## 🎯 Escenarios de Uso

### Escenario 1: Cheque Emitido, Pago Pendiente

**Situación:** Emito un cheque hoy, pero no sé cuándo se pagará.

**Pasos:**
1. Click en "Nuevo Cheque"
2. Ingreso datos:
   - Número: 001234
   - Monto: $5000
   - Fecha de Emisión: **15/01/2025**
   - Fecha de Pago: **DEJAR VACÍO**
   - Banco: Banco Nación
   - Beneficiario: Juan García
3. Guardar → Cheque creado como **Pendiente**

**Más tarde, cuando se pagó:**
- Usar botón 💰 para marcar como pagado (como antes)
- O editar el cheque y agregar la fecha de pago

---

### Escenario 2: Cheque Pagado en el Mismo Día

**Situación:** Emito y pago un cheque el mismo día.

**Pasos:**
1. Click en "Nuevo Cheque"
2. Ingreso datos:
   - Número: 001235
   - Monto: $2000
   - Fecha de Emisión: **15/01/2025**
   - Fecha de Pago: **15/01/2025** ← AGREGADO AHORA
   - Banco: BBVA
   - Beneficiario: María López
3. Guardar → Cheque creado como **Pagado automáticamente**

✅ **Ventaja:** No necesito pasos adicionales, el sistema sabe que ya está pagado.

---

### Escenario 3: Editar Cheque Pendiente

**Situación:** Tengo un cheque pendiente y acabo de pagar.

**Pasos:**
1. En la lista de cheques, click en ✎ (editar)
2. Bajo en la página hasta "Fecha de Pago"
3. Ingreso la fecha: **20/01/2025**
4. Guardar → Cheque actualizado a **Pagado**

**Nota:** Este método es diferente a usar el botón 💰 que abre un formulario especial de pago.

---

## 📊 Comparación de Métodos

| Método | Cuándo Usar | Ventaja |
|--------|-----------|---------|
| **Crear con Fecha de Pago** | Sé que se pagará hoy | Una sola pantalla |
| **Botón 💰 (cheques_pagar.php)** | Pago conocido después | Formulario especial con observaciones |
| **Editar + Fecha de Pago** | Debo cambiar datos | Edito todo en una sola pantalla |

---

## ⚠️ Validaciones

### Validación 1: Fecha de Pago Posterior a Emisión
❌ **NO PERMITIDO:**
```
Fecha Emisión: 20/01/2025
Fecha Pago:    15/01/2025  ← Error! "no puede ser anterior"
```

✅ **PERMITIDO:**
```
Fecha Emisión: 20/01/2025
Fecha Pago:    20/01/2025 (mismo día)
```

### Validación 2: Campos Requeridos
Los siguientes campos siguen siendo **obligatorios:**
- Número de Cheque ✓
- Monto ✓
- Fecha de Emisión ✓
- Banco ✓
- Beneficiario ✓

El campo **Fecha de Pago** sigue siendo **opcional**.

---

## 🔍 Ver Cheques Pagados

### En la Lista Principal
```
N° Cheque | Beneficiario | Monto  | Fecha Emisión | Estado
001234    | Juan García  | $5.000 | 15/01/2025    | ✓ Pagado
                                                     | 18/01/2025

001235    | María López  | $2.000 | 15/01/2025    | ⏳ Pendiente
```

- Cheques con fecha de pago muestran: **✓ Pagado** + fecha
- Cheques sin fecha de pago muestran: **⏳ Pendiente**

---

## 💡 Tips de Eficiencia

### Tip 1: Entrada Rápida de Cheques Pagados
Si muchos cheques se pagan el mismo día:
```
1. Crear cheque CON fecha de pago
2. Listo en una pantalla
3. No necesito pasos adicionales
```

### Tip 2: Cambiar Fecha de Pago
Si me equivoqué al ingresar la fecha:
```
1. Click en ✎ (editar)
2. Cambio la Fecha de Pago
3. Guardo → Actualizado
```

### Tip 3: Deshacer Pago
Si registré un pago por error:
```
1. Click en ✎ (editar)
2. Borro la Fecha de Pago (dejar vacío)
3. Guardo → Vuelve a Pendiente
```

---

## 📱 Interfaces Visuales

### Formulario: Crear Cheque
```
┌─────────────────────────────────────┐
│         NUEVO CHEQUE                │
├─────────────────────────────────────┤
│ N° Cheque*          | 001234         │
│ Monto*              | $ 5000         │
├─────────────────────────────────────┤
│ Fecha de Emisión*   | 15/01/2025     │
│ Fecha de Pago       | [vacío]        │ ← NUEVO
├─────────────────────────────────────┤
│ Banco*              | Banco Nación   │
├─────────────────────────────────────┤
│ Beneficiario*       | Juan García    │
├─────────────────────────────────────┤
│ Observaciones       | (texto)        │
├─────────────────────────────────────┤
│            [Cancelar] [Crear Cheque] │
└─────────────────────────────────────┘
```

### Estado en Listado
```
PENDIENTE:           PAGADO:
⏳ Pendiente        ✓ Pagado
                     18/01/2025
```

---

## 🎓 Flujo Completo: Ejemplo Real

**Lunes 15 de Enero:**
1. Emito cheque a provedor
   ```
   Crear → 001234, $10000, Emisión: 15/01, Pago: [vacío]
   ```
   → Estado: **Pendiente** (porque no sé cuándo se cobra)

**Miércoles 17 de Enero:**
2. El proveedor me avisa que cobró el cheque
   ```
   Editar 001234 → Fecha de Pago: 17/01/2025 → Guardar
   ```
   → Estado: **Pagado 17/01/2025**

**Alternativa más rápida:**
1. Lunes 15 (misma mañana) → Me llama para decir que pasó por el banco
   ```
   Crear → 001234, $10000, Emisión: 15/01, Pago: 15/01
   ```
   → Estado: **Pagado** automáticamente en una sola pantalla ✨

---

## ❓ Preguntas Frecuentes

**P: ¿Puedo dejar Fecha de Pago vacía?**
R: Sí, es totalmente opcional. El cheque se marca como Pendiente.

**P: ¿Qué pasa si ingreso fecha de pago anterior a emisión?**
R: El sistema rechaza y muestra un error.

**P: ¿Puedo cambiar la fecha de pago después?**
R: Sí, editando el cheque.

**P: ¿El botón 💰 de cheques_pagar.php todavía funciona?**
R: Sí, funciona exactamente igual que antes.

**P: ¿Si agrego fecha de pago en crear/editar, puedo usar 💰 después?**
R: No, el botón 💰 solo aparece en cheques Pendientes.

**P: ¿Se puede ver histórico de cambios de fecha?**
R: El sistema solo guarda la fecha actual. Si necesitas histórico, usa observaciones.

---

## 🔒 Seguridad

- ✅ Todos los campos validados en el servidor
- ✅ Validación de fechas contra inyección
- ✅ HTMLSpecialChars aplicado a salidas
- ✅ Prepared statements para queries SQL
- ✅ Control de sesión y permisos mantenido

---

## 📞 Soporte

Si tienes dudas:
1. Revisa este documento
2. Prueba los 3 escenarios principales
3. Nota cualquier comportamiento inesperado

