# Diagnóstico y Solución: Sistema de Cuentas

## Problemática Identificada

Algo no terminó de crearse correctamente en el sistema de cuentas:
- ❌ Tabla `cuentas` no existe
- ❌ Columna `flujo_caja.cuenta_id` no existe  
- ❌ Columna `gastos.cuenta_id` no existe
- ❌ Cuenta "Caja / Operativa" por defecto no se creó

## Probables Causas

**1. Permisos insuficientes del usuario MySQL (MÁS PROBABLE)**

El usuario `Roco` que se usa para conectar a la BD no tiene permisos suficientes para:
- `CREATE TABLE` - crear la tabla `cuentas`
- `ALTER TABLE` - agregar columnas a `flujo_caja` y `gastos`
- `INSERT` - crear la cuenta por defecto

**2. Conexión perdida**

La conexión a la BD se perdió durante la ejecución.

## Cómo Diagnosticar

### Opción 1: Usar el script de diagnóstico (Recomendado)

1. Accede a: `https://tudominio.com/ecommerce/admin/diagnostico_cuentas.php`
2. El script intentará ejecutar cada paso individualmente
3. Mostrará exactamente dónde falla y por qué
4. Copia los errores específicos para el administrador de BD

### Opción 2: Revisar los logs de PHP del servidor

Los errores se registran en el log de PHP con el prefijo `cuentas_helper:`. Busca en:
- `/var/log/php-fpm/error.log`
- `/var/log/php.log`
- U otro path configurado en `php.ini`

Comando para buscar:
```bash
tail -100 /var/log/php-fpm/error.log | grep "cuentas_helper"
```

## Solución: Dar Permisos al Usuario MySQL

Si el problema es de permisos, el administrador de BD debe conectarse como root y ejecutar:

```sql
-- Ver permisos actuales
SHOW GRANTS FOR 'Roco'@'localhost';

-- Si hace falta, agregar permisos (ejecutar siendo root o BD admin):
GRANT CREATE, ALTER, DROP ON tucuroller_produccion.* TO 'Roco'@'149.50.133.145';
GRANT INSERT, UPDATE, DELETE, SELECT ON tucuroller_produccion.* TO 'Roco'@'149.50.133.145';
FLUSH PRIVILEGES;

-- Verificar que se asignaron correctamente:
SHOW GRANTS FOR 'Roco'@'149.50.133.145';
```

## Solución: Crear las tablas/columnas manualmente

Si prefieres crear manualmente en SQL directamente, ejecuta como admin de BD:

```sql
-- 1. Crear tabla cuentas
CREATE TABLE IF NOT EXISTS cuentas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'Operativa',
    descripcion TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    orden_visual INT NOT NULL DEFAULT 0,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_nombre (nombre),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Agregar columna a flujo_caja
ALTER TABLE flujo_caja ADD COLUMN cuenta_id INT NULL;
ALTER TABLE flujo_caja ADD INDEX idx_cuenta_id (cuenta_id);
ALTER TABLE flujo_caja ADD CONSTRAINT fk_flujo_caja_cuenta 
    FOREIGN KEY (cuenta_id) REFERENCES cuentas(id);

-- 3. Agregar columna a gastos
ALTER TABLE gastos ADD COLUMN cuenta_id INT NULL;

-- 4. Crear cuenta por defecto
INSERT INTO cuentas (nombre, tipo, descripcion, activo)
VALUES ('Caja / Operativa', 'Operativa', 'Cuenta por defecto para movimientos históricos y sin cuenta asignada', 1);

-- 5. Backfill: Asignar la cuenta por defecto a registros históricos sin cuenta
UPDATE flujo_caja SET cuenta_id = 1 WHERE cuenta_id IS NULL;
```

## Después de resolver el problema

1. Accede nuevamente a `setup_cuentas.php` o `diagnostico_cuentas.php`
2. Verifica que todos los pasos muestren ✅ OK
3. La sección de diagnóstico debe marcar:
   - ✅ Tabla `cuentas` existe
   - ✅ Columna `flujo_caja.cuenta_id` existe
   - ✅ Columna `gastos.cuenta_id` existe
   - ✅ Cuenta "Caja / Operativa" creada

## Archivos relacionados

- `ecommerce/admin/setup_cuentas.php` - Página de setup visual
- `ecommerce/admin/diagnostico_cuentas.php` - Script de diagnóstico detallado
- `ecommerce/admin/includes/cuentas_helper.php` - Lógica de creación (contiene los error_log)
- `ecommerce/admin/cuentas.php` - Gestión de cuentas (una vez que el esquema está creado)

---

**Nota**: Los error_logs específicos de `cuentas_helper:` te dirán exactamente qué operación falló y por qué. Revisa logs después de acceder a setup_cuentas.php.
