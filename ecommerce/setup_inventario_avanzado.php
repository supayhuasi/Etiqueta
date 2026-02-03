<?php
require '../config.php';

try {
    // Agregar columnas a ecommerce_materiales
    $pdo->exec("
        ALTER TABLE ecommerce_materiales 
        ADD COLUMN IF NOT EXISTS tipo_origen ENUM('fabricacion_propia', 'compra') DEFAULT 'compra',
        ADD COLUMN IF NOT EXISTS stock_minimo DECIMAL(10,2) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS proveedor_habitual_id INT NULL,
        ADD COLUMN IF NOT EXISTS unidad_medida VARCHAR(20) DEFAULT 'unidad'
    ");
    echo "✓ Columnas agregadas a ecommerce_materiales<br>";

    // Agregar columnas a ecommerce_productos
    $pdo->exec("
        ALTER TABLE ecommerce_productos 
        ADD COLUMN IF NOT EXISTS tipo_origen ENUM('fabricacion_propia', 'compra') DEFAULT 'fabricacion_propia',
        ADD COLUMN IF NOT EXISTS stock_minimo DECIMAL(10,2) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS proveedor_habitual_id INT NULL
    ");
    echo "✓ Columnas agregadas a ecommerce_productos<br>";

    // Tabla de alertas de inventario
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_inventario_alertas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo_item ENUM('material', 'producto') NOT NULL,
            item_id INT NOT NULL,
            tipo_alerta ENUM('stock_bajo', 'stock_negativo', 'sin_stock') NOT NULL,
            stock_actual DECIMAL(10,2) NOT NULL,
            stock_minimo DECIMAL(10,2) NOT NULL,
            fecha_alerta DATETIME DEFAULT CURRENT_TIMESTAMP,
            resuelta TINYINT(1) DEFAULT 0,
            fecha_resolucion DATETIME NULL,
            INDEX idx_tipo_item (tipo_item, item_id),
            INDEX idx_resuelta (resuelta)
        )
    ");
    echo "✓ Tabla ecommerce_inventario_alertas creada<br>";

    // Tabla de historial de movimientos de inventario
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_inventario_movimientos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo_item ENUM('material', 'producto') NOT NULL,
            item_id INT NOT NULL,
            tipo_movimiento ENUM('entrada', 'salida', 'ajuste', 'produccion', 'venta') NOT NULL,
            cantidad DECIMAL(10,2) NOT NULL,
            stock_anterior DECIMAL(10,2) NOT NULL,
            stock_nuevo DECIMAL(10,2) NOT NULL,
            referencia VARCHAR(100) NULL COMMENT 'ID de pedido, orden de producción, etc',
            observaciones TEXT NULL,
            usuario_id INT NULL,
            fecha_movimiento DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tipo_item (tipo_item, item_id),
            INDEX idx_fecha (fecha_movimiento)
        )
    ");
    echo "✓ Tabla ecommerce_inventario_movimientos creada<br>";

    echo "<br>✅ Setup de inventario avanzado completado exitosamente";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
