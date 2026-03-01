<?php
/**
 * Setup para Códigos de Barras por Producto en Órdenes de Producción
 * Crea tabla para almacenar códigos únicos de cada item producido
 */

require '../config.php';

try {
    // Tabla para códigos de barras individuales de productos en producción
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_produccion_items_barcode (
            id INT PRIMARY KEY AUTO_INCREMENT,
            orden_produccion_id INT NOT NULL,
            pedido_item_id INT NOT NULL,
            numero_item INT NOT NULL COMMENT 'Número secuencial del item (1 de 5, 2 de 5, etc)',
            codigo_barcode VARCHAR(50) NOT NULL UNIQUE,
            estado ENUM('en_corte','armado','terminado','entregado','rechazado') DEFAULT 'en_corte',
            usuario_inicio INT NULL COMMENT 'Quién inició este item',
            fecha_inicio DATETIME NULL,
            usuario_termino INT NULL COMMENT 'Quién terminó este item',
            fecha_termino DATETIME NULL,
            observaciones TEXT NULL,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (orden_produccion_id) REFERENCES ecommerce_ordenes_produccion(id) ON DELETE CASCADE,
            FOREIGN KEY (pedido_item_id) REFERENCES ecommerce_pedido_items(id) ON DELETE CASCADE,
            FOREIGN KEY (usuario_inicio) REFERENCES usuarios(id) ON DELETE SET NULL,
            FOREIGN KEY (usuario_termino) REFERENCES usuarios(id) ON DELETE SET NULL,
            INDEX idx_orden (orden_produccion_id),
            INDEX idx_codigo (codigo_barcode),
            INDEX idx_estado (estado)
        )
    ");
    
    echo "✓ Tabla ecommerce_produccion_items_barcode creada<br>";
    
    // Agregar columna a ordenes_produccion para trackear items individuales
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_ordenes_produccion LIKE 'items_generados'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_ordenes_produccion ADD COLUMN items_generados TINYINT DEFAULT 0 AFTER materiales_descontados");
        echo "✓ Columna items_generados agregada a ecommerce_ordenes_produccion<br>";
    }
    
    echo "<br><div class='alert alert-success'>✅ Setup de códigos de barras por producto completado</div>";
    echo "<p><a href='admin/ordenes_produccion.php' class='btn btn-primary'>Ir a Órdenes de Producción</a></p>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
