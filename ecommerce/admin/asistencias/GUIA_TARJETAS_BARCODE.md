# 📱 Sistema de Tarjetas con Código de Barras para Asistencias

## 🎯 Descripción General

Sistema completo para generar tarjetas de identificación con códigos de barras e interfaz de escaneo para registrar asistencias de empleados de forma rápida y eficiente.

## 📋 Componentes del Sistema

### 1. **Generador de Tarjetas PDF** (`tarjetas_pdf.php`)
Genera tarjetas de identificación en formato PDF con:
- Código de barras único por empleado (formato Code128)
- Información del empleado (nombre, puesto, departamento)
- Formato estándar de tarjeta (85.6mm x 53.98mm)
- Diseño profesional y claro

### 2. **Interfaz de Escaneo** (`escanear_asistencia.php`)
Pantalla optimizada para registro de asistencias:
- Campo de entrada optimizado para lectores de código de barras
- Visualización en tiempo real del reloj
- Retroalimentación visual y auditiva
- Modo pantalla completa
- Lista de asistencias registradas en el día

### 3. **Procesador de Asistencias** (`escanear_asistencia_procesar.php`)
API backend que:
- Valida códigos de barras escaneados
- Registra asistencias automáticamente
- Calcula estado (presente/tarde) según horarios configurados
- Previene registros duplicados del mismo día

## 🚀 Cómo Usar el Sistema

### Paso 1: Generar Tarjetas

#### Opción A: Todas las tarjetas
```
http://tu-dominio.com/ecommerce/admin/asistencias/tarjetas_pdf.php?todos=1
```

#### Opción B: Tarjeta individual
```
http://tu-dominio.com/ecommerce/admin/asistencias/tarjetas_pdf.php?empleado_id=1
```

**Desde la interfaz:**
1. Ir a **Asistencias** → **Control de Asistencias**
2. Hacer clic en el botón **🎫 Tarjetas PDF**
3. Seleccionar:
   - "Todas las Tarjetas" para descargar todas
   - Un empleado específico para su tarjeta individual

### Paso 2: Imprimir y Plastificar

1. **Descargar el PDF** generado
2. **Imprimir** en papel de buena calidad (idealmente cartulina)
3. **Recortar** por las líneas del borde
4. **Plastificar** para mayor durabilidad (recomendado)
5. **Entregar** a cada empleado

### Paso 3: Configurar Estación de Escaneo

#### Hardware Necesario:
- **Computadora o tablet** con navegador web
- **Lector de código de barras** USB o Bluetooth
   - Compatible con Code128
   - Configurado para enviar Enter después de escanear

#### Software:
1. Acceder a: `/ecommerce/admin/asistencias/escanear_asistencia.php`
2. Activar **modo pantalla completa** (botón superior derecho)
3. El sistema está listo para usar

### Paso 4: Registrar Asistencias

1. **Empleado presenta su tarjeta**
2. **Escanear el código de barras**
   - El sistema detecta automáticamente el código
   - No es necesario presionar ningún botón
3. **Sistema muestra confirmación:**
   - ✅ Verde: Registro exitoso
   - ⚠️ Amarillo: Llegada tardía
   - ❌ Rojo: Error (ya registrado, empleado no encontrado, etc.)
4. **Sonido de confirmación** indica resultado
5. **El sistema está listo** para el siguiente escaneo

## 📊 Formato del Código de Barras

### Estructura
```
EMP8F2A91C4D03B
EMP1A7C3E9B55F0
```


 

## ⚙️ Configuración del Sistema

### Horarios y Tolerancia

El sistema utiliza la configuración de horarios existente:

```sql
-- Ver horarios configurados
SELECT e.nombre, 
       eh.hora_entrada, 
       eh.hora_salida, 
       eh.tolerancia_minutos
FROM empleados e
LEFT JOIN empleados_horarios eh ON e.id = eh.empleado_id
WHERE e.activo = 1;
```

### Estados de Asistencia

- **Presente:** Llegó dentro del horario + tolerancia
- **Tarde:** Llegó después de la tolerancia
- **Ausente:** No registró asistencia (se marca manualmente)
- **Justificado:** Falta justificada (se marca manualmente)

## 🔧 Configuración del Lector de Código de Barras

### Configuración Recomendada:

1. **Sufijo:** Enter (CR o LF)
2. **Prefijo:** Ninguno
3. **Velocidad:** Normal
4. **Tipo:** Code128 habilitado
5. **Modo:** Teclado (keyboard wedge)

### Prueba del Lector:

1. Abrir un editor de texto (Notepad, gedit, etc.)
2. Escanear un código de barras
3. Debe aparecer el código y cambiar de línea automáticamente

## 🎨 Personalización de Tarjetas

Para personalizar el diseño de las tarjetas, editar `tarjetas_pdf.php`:

```php
// Logo de empresa
$pdf->Image('logo.png', $x + 5, $y + 5, 20);

// Colores corporativos
$pdf->SetFillColor(0, 123, 255); // RGB

// Fuentes
$pdf->SetFont('Arial', 'B', 14);
```

## 📱 Interfaz de Escaneo - Características

### Características Principales:

- ✅ **Auto-foco:** El campo de entrada siempre está listo
- ✅ **Validación en tiempo real:** Respuesta inmediata al escaneo
- ✅ **Feedback auditivo:** Sonidos distintos para éxito/error
- ✅ **Lista actualizada:** Muestra asistencias del día en tiempo real
- ✅ **Modo kiosko:** Pantalla completa para uso dedicado
- ✅ **Sin duplicados:** Previene múltiples registros del mismo día

### Atajos de Teclado:

- **F11:** Pantalla completa (navegador)
- **ESC:** Salir de pantalla completa
- **Escanear código:** Registro automático

## 🔒 Seguridad

### Validaciones Implementadas:

1. ✅ Verificación de formato de código
2. ✅ Validación de empleado activo
3. ✅ Prevención de duplicados diarios
4. ✅ Registro de usuario que procesó (auditoría)
5. ✅ Sanitización de entradas

### Auditoría:

Cada registro guarda:
- Fecha y hora exacta
- Empleado que registró
- Usuario del sistema (si aplica)
- Estado calculado automáticamente

## 🎯 Casos de Uso

### Uso 1: Entrada de Turno Matutino
```
07:55 → Empleado escanea → ✅ PRESENTE
08:12 → Empleado escanea → ⚠️ TARDE (tolerancia: 10 min)
```

### Uso 2: Múltiples Empleados
```
Empleado A → Escanea → Registro OK
Empleado B → Escanea → Registro OK
Empleado A → Escanea de nuevo → ❌ Ya registrado hoy
```

### Uso 3: Reporte Diario
La interfaz muestra en tiempo real todas las asistencias registradas en el día.

## 📊 Integración con Reportes

Los registros de asistencias por escaneo se integran automáticamente con:

- 📊 **Reportes mensuales** de asistencias
- 💰 **Cálculo de sueldos** (basado en asistencias)
- 📈 **Estadísticas** de puntualidad
- 📅 **Histórico** de asistencias

## 🛠️ Solución de Problemas

### Problema: Lector no funciona
**Solución:**
- Verificar conexión USB/Bluetooth
- Probar en editor de texto
- Revisar que esté configurado en modo teclado
- Verificar que Code128 esté habilitado

### Problema: Código no se reconoce
**Solución:**
- Verificar que el código comience con `EMP`
- Limpiar la superficie del código de barras
- Acercar más el lector
- Re-generar tarjeta si está dañada

### Problema: Empleado no encontrado
**Solución:**
- Verificar que el empleado esté activo en el sistema
- Confirmar que el ID del código coincide con la base de datos
- Re-generar tarjeta con el ID correcto

### Problema: Pantalla se apaga
**Solución:**
- Configurar sistema para NO suspender/apagar pantalla
- En Linux: `xset s off -dpms`
- En Windows: Panel de Control → Energía → Nunca apagar

## 📞 Soporte Técnico

### Logs del Sistema

Ver errores en:
```
/logs/asistencias_error.log
```

### Base de Datos

Tabla principal: `asistencias`

```sql
-- Ver registros recientes
SELECT a.*, e.nombre 
FROM asistencias a
JOIN empleados e ON a.empleado_id = e.id
ORDER BY a.fecha_creacion DESC
LIMIT 20;
```

## 🚀 Mejoras Futuras

Ideas para expandir el sistema:

- [ ] Registro de salida (escaneo al finalizar jornada)
- [ ] Notificaciones push para tardanzas
- [ ] Dashboard con estadísticas en vivo
- [ ] Integración con control de acceso físico
- [ ] App móvil para supervisores
- [ ] Reconocimiento facial como alternativa
- [ ] Geolocalización de registros
- [ ] Exportación a Excel de asistencias diarias

## 📝 Notas Importantes

1. **Lectores compatibles:** La mayoría de lectores USB son compatibles
2. **Navegadores:** Chrome, Firefox, Edge (últimas versiones)
3. **Impresión:** Usar impresora de buena calidad para códigos legibles
4. **Mantenimiento:** Reemplazar tarjetas si el código se daña
5. **Respaldo:** Mantener copia digital de las tarjetas generadas

---

**Desarrollado para:** Sistema de Gestión de Asistencias  
**Versión:** 1.0  
**Fecha:** Febrero 2026
