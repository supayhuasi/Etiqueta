# ✅ Checklist de Verificación - Precios por Opción

## Antes de comenzar

- [ ] Has ejecutado la migración `ecommerce/admin/migrar_atributo_opciones.php` (si no existe la tabla)
- [ ] Tienes al menos 1 producto con atributos tipo "select"

## 1. Verificar en Admin Panel

### Paso 1: Navega a Atributos
```
Admin → Productos → Selecciona un Producto → Click en botón 🖼️ (Atributos)
```

### Paso 2: Verifica que veas los badges de costo
- [ ] Las opciones muestran un **badge verde con costo** (ej: `+$50.00`)
- [ ] Las opciones sin costo muestran `Gratis` en gris
- [ ] Puedes editar cada opción y cambiar su costo

### Paso 3: Crea una opción con costo
```
1. Click en "Agregar Opción"
2. Nombre: "Opción Premium"
3. Costo: 75.00
4. Click Guardar
```
- [ ] La opción se creó correctamente
- [ ] Aparece el badge `+$75.00`

## 2. Verificar en la Tienda

### Paso 1: Abre un producto con atributos
```
Tienda → Selecciona un producto con atributos tipo select
```

### Paso 2: Verifica los badges de costo
- [ ] Cada opción tiene un badge verde si tiene costo (ej: `+$50.00`)
- [ ] Las opciones sin costo NO tienen badge
- [ ] Los badges están posicionados en la esquina superior derecha

### Paso 3: Prueba el cálculo dinámico
```
1. Selecciona una opción SIN costo
   └─ El precio NO debe cambiar
   
2. Selecciona una opción CON costo
   └─ El precio debe aumentar
   └─ Se debe mostrar desglose: "+ Opción: $50.00"
   
3. Selecciona otra opción CON costo diferente
   └─ El precio debe sumar ambos costos
```

- [ ] El precio se actualiza correctamente
- [ ] Se muestra el desglose de costos
- [ ] El cálculo es correcto

### Paso 4: Verifica el desglose visual
```
Precio: $500.00
+ Opción 1: $50.00
+ Opción 2: $30.00
```
- [ ] Se muestran todos los costos agregados
- [ ] El total es correcto ($580.00 en este ejemplo)

## 3. Verificar en el Carrito

### Paso 1: Agrega producto al carrito
```
1. Selecciona opciones con costo
2. Click "Agregar al Carrito"
```

### Paso 2: Abre el carrito
```
Tienda → Carrito
```

### Paso 3: Verifica la visualización
- [ ] Se muestra el precio base del producto
- [ ] Se muestran las opciones con sus costos (ej: `+$50.00`)
- [ ] La columna "Precio" tiene:
  - Precio base
  - `+$X.XX` para cada opción
  - Precio final en VERDE

Ejemplo:
```
| Cortina          | $500.00      | 1 | $550.00 |
|                  | +$50.00      |   |         |
|                  | $550.00      |   |         |
```

### Paso 4: Verifica el cálculo
- [ ] Subtotal = Precio base + todos los costos × cantidad
- [ ] El total es correcto

## 4. Verificar el Desglose de Costos

### En la tienda:
```
Desglose visible debajo del precio total
├─ + Arandela: $50.00
├─ + Protección: $30.00
└─ + Otro: $20.00
```
- [ ] Se muestran todos los costos en badges
- [ ] Cada badge tiene el nombre y costo correcto

### En el carrito:
```
Columna "Precio" muestra:
├─ $500.00 (base)
├─ +$50.00 (opción)
└─ $550.00 (total)
```
- [ ] Se ve claramente el desglose
- [ ] El total es correcto

## 5. Casos de Prueba

### Caso 1: Múltiples opciones con costo
```
Producto: Cortina
Atributo: Accesorios

Opciones:
├─ Arandela Aluminio → +$50.00 ✓ Selecciona
├─ Protección UV → +$30.00 ✓ Selecciona
├─ Sin accesorios → $0.00

Resultado esperado:
Precio base: $500.00
+ Arandela: $50.00
+ UV: $30.00
= $580.00
```
- [ ] El cálculo es correcto
- [ ] Se muestran ambos costos

### Caso 2: Cambiar selección
```
1. Selecciona opción A (costo: $50)
2. Cambias a opción B (costo: $30)
3. Cambias a opción C (costo: $0)

Cada cambio debe:
```
- [ ] Actualizar el precio al instante
- [ ] Mostrar el desglose correcto
- [ ] Que el total sea correcto

### Caso 3: Múltiples atributos con costo
```
Atributo 1: Accesorios
├─ Arandela → +$50

Atributo 2: Acabado
├─ Brillante → +$20

Selecciona ambos:
```
- [ ] El precio suma ambos costos
- [ ] Se muestra desglose de ambos
- [ ] El total es: Base + $50 + $20

## 6. Verificar Base de Datos

### Abre MySQL/phpMyAdmin

```sql
-- Verifica que la tabla existe
SHOW TABLES LIKE 'ecommerce_atributo_opciones';

-- Verifica que el campo existe
DESCRIBE ecommerce_atributo_opciones;

-- Deberías ver:
-- costo_adicional | decimal(10,2) | YES | NULL | 0

-- Verifica un registro
SELECT id, nombre, costo_adicional 
FROM ecommerce_atributo_opciones 
LIMIT 5;
```

- [ ] La tabla existe
- [ ] El campo `costo_adicional` existe
- [ ] Los costos se guardan correctamente

## 7. Prueba de Seguridad

### Intenta manipular desde el navegador
```
1. Abre las Herramientas de Desarrollador (F12)
2. Ve a la consola
3. Intenta cambiar el precio con JavaScript
```

Al procesar el pago:
- [ ] El servidor recalcula desde la BD
- [ ] Rechaza si los totales no coinciden
- [ ] No permite fraude

## 8. Checklist Final

- [ ] Admin: Puedo asignar costos a opciones
- [ ] Admin: Los badges de costo se muestran
- [ ] Tienda: Veo los badges en las opciones
- [ ] Tienda: El precio se actualiza al seleccionar
- [ ] Tienda: Se muestra el desglose de costos
- [ ] Carrito: Los costos se muestran en el desglose
- [ ] Carrito: El subtotal es correcto
- [ ] Múltiples opciones se suman correctamente
- [ ] Cambiar opciones actualiza el precio
- [ ] La base de datos tiene los costos guardados

## ⚠️ Problemas Comunes

### Problema: No veo los badges de costo
**Solución:**
1. Verifica que la opción tenga `costo_adicional > 0`
2. Recarga la página (Ctrl+F5)
3. Verifica en BD que el campo tiene valor

### Problema: El precio no se actualiza
**Solución:**
1. Verifica que JavaScript esté habilitado
2. Abre la consola (F12) para ver errores
3. Verifica que `atributosData` tenga los costos cargados

### Problema: El carrito muestra precio incorrecto
**Solución:**
1. Verifica que el servidor calculó bien al agregar
2. Abre el carrito y verifica los costos en el desglose
3. Recalcula manualmente para verificar

### Problema: La migración no se ejecutó
**Solución:**
1. Ve a: `ecommerce/admin/migraciones.php`
2. O ejecuta manualmente en BD:
```sql
ALTER TABLE ecommerce_atributo_opciones 
ADD COLUMN costo_adicional DECIMAL(10,2) DEFAULT 0 
AFTER color;
```

---

**¿Necesitas ayuda?**
Revisa los archivos de documentación:
- `PRECIOS_ESPECIALES_OPCIONES.md` - Documentación completa
- `DIAGRAMA_FLUJO_PRECIOS.md` - Diagramas del sistema
- `CAMBIOS_PRECIOS_OPCIONES.md` - Resumen de cambios

**Última actualización:** 3 de Febrero, 2026
