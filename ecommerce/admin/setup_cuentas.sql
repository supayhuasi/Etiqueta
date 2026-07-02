-- ============================================================================
-- Script robusto para crear/corregir el sistema de cuentas en la base de datos
-- ============================================================================
-- Ejecutar como administrador de BD o usuario con permisos CREATE/ALTER/INSERT/UPDATE
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

-- 2. AGREGAR COLUMNA cuenta_id A flujo_caja SI NO EXISTE
-- ============================================================================
SET @stmt := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'flujo_caja'
    ) = 1
    AND (
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'flujo_caja' AND column_name = 'cuenta_id'
    ) = 0,
    'ALTER TABLE flujo_caja ADD COLUMN cuenta_id INT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. AGREGAR ÍNDICE idx_cuenta_id A flujo_caja SI NO EXISTE
-- ============================================================================
SET @stmt := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'flujo_caja' AND index_name = 'idx_cuenta_id'
    ) = 0,
    'ALTER TABLE flujo_caja ADD INDEX idx_cuenta_id (cuenta_id)',
    'SELECT 1'
);
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. AGREGAR FOREIGN KEY fk_flujo_caja_cuenta SI NO EXISTE
-- ============================================================================
SET @stmt := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.table_constraints
        WHERE table_schema = DATABASE() AND table_name = 'flujo_caja' AND constraint_name = 'fk_flujo_caja_cuenta'
    ) = 0,
    'ALTER TABLE flujo_caja ADD CONSTRAINT fk_flujo_caja_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas(id)',
    'SELECT 1'
);
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. AGREGAR COLUMNA cuenta_id A gastos SI NO EXISTE
-- ============================================================================
SET @stmt := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'gastos'
    ) = 1
    AND (
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'gastos' AND column_name = 'cuenta_id'
    ) = 0,
    'ALTER TABLE gastos ADD COLUMN cuenta_id INT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. CREAR CUENTA POR DEFECTO SI NO EXISTE
-- ============================================================================
INSERT INTO cuentas (nombre, tipo, descripcion, activo)
SELECT 'Caja / Operativa', 'Operativa', 'Cuenta por defecto para movimientos históricos y sin cuenta asignada', 1
WHERE NOT EXISTS (
    SELECT 1 FROM cuentas WHERE nombre = 'Caja / Operativa'
);

-- 7. BACKFILL - ASIGNAR CUENTA POR DEFECTO A REGISTROS HISTÓRICOS
-- ============================================================================
SET @default_cuenta_id := (SELECT id FROM cuentas WHERE nombre = 'Caja / Operativa' LIMIT 1);

-- Evitar el error 1175 de MySQL Safe Update Mode en Workbench/phpMyAdmin
SET SQL_SAFE_UPDATES = 0;

UPDATE flujo_caja
SET cuenta_id = @default_cuenta_id
WHERE id IS NOT NULL AND cuenta_id IS NULL;

UPDATE gastos
SET cuenta_id = @default_cuenta_id
WHERE id IS NOT NULL AND cuenta_id IS NULL;

SET SQL_SAFE_UPDATES = 1;

-- 8. VERIFICACIÓN FINAL
-- ============================================================================
SELECT 'Tabla cuentas' AS verificacion, COUNT(*) AS cantidad FROM cuentas;
SELECT 'Registros con cuenta_id en flujo_caja' AS verificacion, COUNT(*) AS cantidad FROM flujo_caja WHERE cuenta_id IS NOT NULL;
SELECT 'Registros sin cuenta_id en flujo_caja' AS verificacion, COUNT(*) AS cantidad FROM flujo_caja WHERE cuenta_id IS NULL;
SELECT 'Columna flujo_caja.cuenta_id existe' AS verificacion, COLUMN_NAME FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'flujo_caja' AND column_name = 'cuenta_id' LIMIT 1;
SELECT 'Columna gastos.cuenta_id existe' AS verificacion, COLUMN_NAME FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'gastos' AND column_name = 'cuenta_id' LIMIT 1;
SELECT 'Cuenta por defecto' AS verificacion, id, nombre FROM cuentas WHERE nombre = 'Caja / Operativa';

-- ============================================================================
-- Si lo ejecutás desde phpMyAdmin o MySQL Workbench, seleccioná la base
-- correspondiente antes de correr el script.
-- ============================================================================
