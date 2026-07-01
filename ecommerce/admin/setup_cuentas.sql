-- ============================================================================
-- Script para crear/corregir el sistema de cuentas en tucuroller_produccion
-- ============================================================================
-- Ejecutar como administrador de BD o usuario con permisos CREATE/ALTER
-- ============================================================================

-- 1. CREAR TABLA CUENTAS
-- ============================================================================
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


-- 2. AGREGAR COLUMNA A FLUJO_CAJA (si no existe)
-- ============================================================================
-- Primero verificar si la tabla existe
-- Si existe pero no la columna, agregarla
ALTER TABLE flujo_caja ADD COLUMN cuenta_id INT NULL;

-- Agregar índice
ALTER TABLE flujo_caja ADD INDEX idx_cuenta_id (cuenta_id);

-- Agregar Foreign Key (solo si no existe)
ALTER TABLE flujo_caja ADD CONSTRAINT fk_flujo_caja_cuenta 
    FOREIGN KEY (cuenta_id) REFERENCES cuentas(id);


-- 3. AGREGAR COLUMNA A GASTOS (si no existe)
-- ============================================================================
ALTER TABLE gastos ADD COLUMN cuenta_id INT NULL;


-- 4. CREAR CUENTA POR DEFECTO
-- ============================================================================
-- Solo insertar si no existe
INSERT IGNORE INTO cuentas (nombre, tipo, descripcion, activo)
VALUES ('Caja / Operativa', 'Operativa', 'Cuenta por defecto para movimientos históricos y sin cuenta asignada', 1);


-- 5. BACKFILL - Asignar cuenta por defecto a registros históricos
-- ============================================================================
-- Obtener el ID de la cuenta por defecto
SET @default_cuenta_id = (SELECT id FROM cuentas WHERE nombre = 'Caja / Operativa' LIMIT 1);

-- Asignar a flujo_caja
UPDATE flujo_caja SET cuenta_id = @default_cuenta_id WHERE cuenta_id IS NULL;

-- Asignar a gastos (si la tabla tiene registros históricos sin cuenta)
UPDATE gastos SET cuenta_id = @default_cuenta_id WHERE cuenta_id IS NULL;


-- 6. VERIFICACIÓN FINAL
-- ============================================================================
-- Ejecutar estos SELECT para verificar que todo está correcto:

SELECT 'Tabla cuentas' as verificacion, COUNT(*) as cantidad FROM cuentas;
SELECT 'Registros con cuenta_id en flujo_caja' as verificacion, COUNT(*) as cantidad FROM flujo_caja WHERE cuenta_id IS NOT NULL;
SELECT 'Registros sin cuenta_id en flujo_caja' as verificacion, COUNT(*) as cantidad FROM flujo_caja WHERE cuenta_id IS NULL;
SELECT 'Columna flujo_caja.cuenta_id existe' as verificacion, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'flujo_caja' AND COLUMN_NAME = 'cuenta_id' LIMIT 1;
SELECT 'Columna gastos.cuenta_id existe' as verificacion, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'gastos' AND COLUMN_NAME = 'cuenta_id' LIMIT 1;
SELECT 'Cuenta por defecto' as verificacion, id, nombre FROM cuentas WHERE nombre = 'Caja / Operativa';


-- ============================================================================
-- PERMISOS NECESARIOS PARA EL USUARIO 'Roco'
-- ============================================================================
-- Si accedes desde el host 149.50.133.145, ejecuta como root/admin:

-- GRANT CREATE, ALTER, DROP ON tucuroller_produccion.* TO 'Roco'@'149.50.133.145';
-- GRANT INSERT, UPDATE, DELETE, SELECT ON tucuroller_produccion.* TO 'Roco'@'149.50.133.145';
-- FLUSH PRIVILEGES;

-- Para verificar permisos:
-- SHOW GRANTS FOR 'Roco'@'149.50.133.145';
