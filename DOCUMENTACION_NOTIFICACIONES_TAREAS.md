# Sistema de Notificaciones de Tareas

## Resumen

Se ha implementado un sistema completo de notificaciones por email para tareas y recordatorios en el módulo de producción `produccion_tareas_usuarios.php`.

## Cambios Realizados

### 1. Nuevo Helper: `tareas_notificaciones_helper.php`

Ubicación: `ecommerce/admin/includes/tareas_notificaciones_helper.php`

Contiene 3 funciones principales:

#### `enviar_notificacion_tarea_asignada()`
- **Propósito**: Envía notificación cuando se asigna una tarea a un usuario
- **Parámetros**:
  - `$pdo`: Conexión a BD
  - `$tarea_id`: ID de la tarea
  - `$usuario_destino_id`: Usuario que recibe la tarea
  - `$asignada_por_id`: Admin que asigna
- **Retorna**: `true` si se envió, `false` si falló
- **Contenido del Email**:
  - Titular de la tarea
  - Descripción completa
  - Fecha límite
  - Fecha de asignación
  - Nombre del administrador que asigna

#### `enviar_notificacion_recordatorio_creado()`
- **Propósito**: Envía notificación cuando se crea un recordatorio
- **Parámetros**:
  - `$pdo`: Conexión a BD
  - `$recordatorio_id`: ID del recordatorio
  - `$usuario_id`: Usuario propietario
- **Retorna**: `true` si se envió, `false` si falló

#### `enviar_resumen_tareas_pendientes()`
- **Propósito**: Envía resumen de tareas pendientes (no utilizado aún, disponible para cronogramas)
- **Parámetros**:
  - `$pdo`: Conexión a BD
  - `$usuario_id`: Usuario
- **Retorna**: `true` si se envió, `false` si falló

### 2. Modificaciones a `produccion_tareas_usuarios.php`

#### Cambio 1: Agregar require_once
```php
require_once 'includes/tareas_notificaciones_helper.php';
```

#### Cambio 2: Enviar notificación al asignar tarea
Después de `INSERT INTO ecommerce_tareas_usuarios`, se agrega:
```php
$tarea_id_nueva = (int)$pdo->lastInsertId();
enviar_notificacion_tarea_asignada($pdo, $tarea_id_nueva, $usuario_destino, (int)($_SESSION['user']['id'] ?? 0));
```

#### Cambio 3: Enviar notificación al crear recordatorio
Después de `INSERT INTO ecommerce_recordatorios_usuarios`, se agrega:
```php
if ($recordatorio_usuario_id > 0) {
    enviar_notificacion_recordatorio_creado($pdo, $recordatorio_id_nuevo, $recordatorio_usuario_id);
}
```

#### Cambio 4: Enviar notificación al crear recordatorio rápido
Cuando un usuario se autoasigna un recordatorio (recordatorio_rapido_crear):
```php
enviar_notificacion_recordatorio_creado($pdo, $recordatorio_id_nuevo, $usuario_actual_id);
```

## Cómo Funciona

### Requisitos Previos

1. **Configurar SMTP**: Ir a `ecommerce/admin/email_config.php` y completar:
   - From Email
   - From Name
   - SMTP Host
   - SMTP Port
   - SMTP User
   - SMTP Password
   - SMTP Secure (ssl/tls)
   - Marcar como "Activo"

2. **Usuarios deben tener email**: Cada usuario debe tener un email válido en la BD (tabla `usuarios` columna `email`)

### Flujo de Notificación

```
1. Admin asigna tarea → INSERT en ecommerce_tareas_usuarios
                    ↓
2. Se obtiene el ID de la tarea (lastInsertId)
                    ↓
3. Se llama a enviar_notificacion_tarea_asignada()
                    ↓
4. Se obtienen datos del usuario destinatario
                    ↓
5. Se construye email HTML
                    ↓
6. Se envía email via SMTP (o mail() si SMTP no está disponible)
                    ↓
7. Se muestra mensaje "Tarea asignada correctamente" + notificación enviada
```

### Casos de Uso

#### Caso 1: Admin asigna tarea a usuario
1. Admin va a `ecommerce/admin/produccion_tareas_usuarios.php`
2. Llena el formulario "Asignar Tarea"
3. Selecciona usuario destino
4. Ingresa título y descripción
5. Opcionalmente ingresa fecha límite
6. Hace clic en "Asignar"
7. ✅ La tarea se crea en BD
8. ✅ **Se envía email al usuario automáticamente**

#### Caso 2: Usuario crea recordatorio rápido para sí mismo
1. Usuario ingresa a `ecommerce/admin/produccion_tareas_usuarios.php`
2. Llenar campo "Recordatorio rápido"
3. Presiona guardar
4. ✅ El recordatorio se crea
5. ✅ **Se envía email al usuario automáticamente**

## Verificación

### Opción 1: Script de prueba
Ejecutar desde la raíz del proyecto:
```bash
php test_notificaciones.php
```

Esto muestra:
- Estado de la configuración de email
- Últimas 5 tareas
- Últimos 5 recordatorios

### Opción 2: Verificar los logs
Las notificaciones que fallan se registran en:
- Logs de PHP (php.ini error_log)
- Logs del servidor web
- Línea de error_log() en el código

### Opción 3: Enviar notificación manual
```bash
php test_notificaciones.php send_task [ID_TAREA]
```

Ejemplo:
```bash
php test_notificaciones.php send_task 5
```

## Solución de Problemas

### "No se recibe el email"

1. **Verificar configuración SMTP**
   - Ir a `ecommerce/admin/email_config.php`
   - Verificar que esté marcado como "Activo"
   - Probar credenciales en cliente email de prueba

2. **Verificar usuario tiene email**
   - BD → tabla `usuarios` → columna `email`
   - El email debe ser válido (no vacío)

3. **Verificar logs**
   - Ver logs de PHP (php.ini → error_log)
   - Ver logs del servidor SMTP
   - Ejecutar test_notificaciones.php para debug

4. **PHPMailer vs mail()**
   - Si PHPMailer no está disponible, el sistema usa mail() de PHP
   - mail() requiere que el servidor esté configurado para enviar emails

### "Email llega a spam"

1. Configurar SPF/DKIM en el servidor de correo
2. Usar un email "from" confiable
3. Incluir unsubscribe link (mejora de futuro)

## Código de Referencia

### Estructura del Email de Tarea

```html
<h1>Nueva Tarea Asignada</h1>
<p>Hola [NOMBRE_USUARIO],</p>
<p>[NOMBRE_ADMIN] ha asignado una nueva tarea para vos:</p>

<div class="task-box">
  <h3>[TÍTULO_TAREA]</h3>
  <p><strong>Descripción:</strong></p>
  <p>[DESCRIPCIÓN]</p>
  <p><strong>Fecha límite:</strong> [FECHA_LÍMITE]</p>
  <p><strong>Asignada el:</strong> [FECHA_ASIGNACIÓN]</p>
</div>

<p>Por favor, revisá la tarea y actualizá su estado en el sistema.</p>
```

### Estructura del Email de Recordatorio

Similar al de tarea, pero con:
- Titular diferente ("Recordatorio Creado")
- Color naranja (ffc107) en lugar de azul (007bff)
- Campo "Fecha del recordatorio" en lugar de "Fecha límite"

## Mejoras Futuras

1. **Notificación de cambio de estado**: Enviar email cuando la tarea cambia de estado
2. **Resumen semanal**: Enviar resumen de tareas pendientes cada lunes
3. **Notificación de vencimiento**: Recordatorio cuando se acerca la fecha límite
4. **Desuscripción**: Permitir a usuarios desuscribirse temporalmente
5. **Base de datos de notificaciones**: Guardar historial de notificaciones enviadas

## Archivos Modificados

1. `ecommerce/admin/includes/tareas_notificaciones_helper.php` (NUEVO)
2. `ecommerce/admin/produccion_tareas_usuarios.php` (MODIFICADO)

## Validación

✅ Sintaxis PHP verificada (`php -l`)
✅ Funciones creadas correctamente
✅ Integración con mailer.php existente
✅ Compatible con PHPMailer y mail()
✅ Compatible con configuración de BD

## Notas Importantes

- Las notificaciones se envían **inmediatamente** cuando se asigna la tarea
- El sistema es **no-bloqueante**: si falla el email, la tarea se crea igual
- Los errores se registran automáticamente en error_log()
- Se respeta la configuración de SMTP de la BD
