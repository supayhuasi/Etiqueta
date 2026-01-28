# 📋 Mejoras en el Módulo de Asistencias

## Cambios Realizados

### 1. **Sistema de Horarios por Día de la Semana**

Se ha mejorado significativamente el sistema de gestión de horarios para permitir que cada empleado tenga horarios diferentes según el día de la semana.

#### Nueva Tabla: `empleados_horarios_dias`
```sql
CREATE TABLE empleados_horarios_dias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    empleado_id INT NOT NULL,
    dia_semana TINYINT NOT NULL,  -- 0=Domingo, 1=Lunes, ..., 6=Sábado
    hora_entrada TIME NOT NULL,
    hora_salida TIME NOT NULL,
    tolerancia_minutos INT DEFAULT 10,
    activo TINYINT DEFAULT 1,
    ...
)
```

#### Tabla Original Preservada: `empleados_horarios`
- Se mantiene como horario general/predeterminado
- Se usa como fallback cuando no hay horario específico del día

### 2. **Interfaz Mejorada de Horarios**

Archivo: `asistencias_horarios_editar_v2.php`

**Dos Modos de Configuración:**

1. **Horario General** (pestaña "Horario General")
   - Un horario único para todos los días
   - Se usa cuando no hay horarios específicos por día configurados

2. **Por Día de la Semana** (pestaña "Por Día de la Semana")
   - Configuración individual para cada día
   - Lunes a Viernes agrupados en una sección
   - Sábado y Domingo en otra sección
   - Todos los campos opcionales
   - Los horarios por día tienen prioridad sobre el general

**Ejemplo de Uso:**
```
Empleado: Juan Pérez
- Lunes a Viernes: 09:00 - 17:00 (Tolerancia: 10 min)
- Sábado: 10:00 - 14:00 (Tolerancia: 15 min)
- Domingo: Sin trabajo (vacío)
```

### 3. **Detección Automática de Tardanza Mejorada**

El sistema ahora:
1. Obtiene el día de la semana de la fecha registrada
2. Busca primero un horario específico para ese día
3. Si no existe, usa el horario general
4. Compara la hora de entrada con la tolerancia configurada

**Archivos Actualizados:**
- `asistencias_crear.php` - Valida horario específico del día
- `asistencias.php` - Muestra horario correcto en el listado
- `asistencias_reporte.php` - Incluye horario correcto en reportes

### 4. **API AJAX Mejorada**

Archivo: `asistencias_horario_ajax.php`

Ahora recibe:
- `empleado_id` - ID del empleado
- `fecha` - Fecha para determinar el día de la semana

Retorna:
```json
{
  "tiene_horario": true,
  "hora_entrada": "09:00",
  "hora_salida": "17:00",
  "tolerancia": 10,
  "texto": "Horario: 09:00 - 17:00 (Tolerancia: 10 min)"
}
```

### 5. **Actualización de Interfaz de Carga**

En `asistencias_crear.php`:
- El horario ahora se actualiza cuando cambia el empleado
- El horario se actualiza cuando cambia la fecha
- Se muestra el horario específico del día seleccionado

## Flujo Operativo

### Para un Administrador:

1. **Ir a "⏰ Gestionar Horarios"** en Asistencias
2. **Seleccionar un empleado**
3. **Elegir modo:**
   - **Horario General:** Si todos los días tienen mismo horario
   - **Por Día de la Semana:** Si hay variaciones

4. **Guardar** y los cambios se aplican inmediatamente

### Para cargar Asistencia:

1. **Ir a "➕ Cargar Asistencia"**
2. **Seleccionar empleado** → Se carga automáticamente su horario del día
3. **Seleccionar fecha** → Se actualiza el horario si es diferente
4. **Cargar horas** → El sistema detecta automáticamente si llegó tarde

## Información Técnica

### Cálculo de Día de Semana
- MySQL: `DAYOFWEEK(fecha) - 1` retorna el valor 0-6
- Sistema: 0=Domingo, 1=Lunes, ..., 6=Sábado

### Prioridad de Horarios
1. Horario específico del día (empleados_horarios_dias)
2. Horario general (empleados_horarios)
3. Sin horario (permite carga sin validación)

### Compatibilidad
- Los empleados sin horarios por día continuarán usando el horario general
- Los registros anteriores siguen siendo válidos
- No se pierden datos durante la migración

## Archivos Modificados

1. ✅ `setup_asistencias.php` - Agregó tabla empleados_horarios_dias
2. ✅ `asistencias_horarios_editar_v2.php` - Nueva interfaz mejorada
3. ✅ `asistencias.php` - Query mejorada con COALESCE
4. ✅ `asistencias_crear.php` - Lógica de tardanza mejorada
5. ✅ `asistencias_reporte.php` - Query mejorada con COALESCE
6. ✅ `asistencias_horario_ajax.php` - API mejorada
7. ✅ `asistencias_horarios.php` - Links actualizados

## Ejemplo de Registro

**Empleado:** María García
- **Horario Lunes-Viernes:** 08:30 - 16:30
- **Horario Sábado:** 09:00 - 13:00

**Registro:**
- Martes 26/01, entrada 08:45 → Presente (dentro de tolerancia)
- Viernes 29/01, entrada 08:20 → Presente (entrada anticipada)
- Sábado 30/01, entrada 09:15 → Tarde (pasó tolerancia de sábado)

El sistema detecta automáticamente cada caso según el día específico.
