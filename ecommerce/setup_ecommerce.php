<?php
require '../config.php';

try {
    // Tabla de categorías de productos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_categorias (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(100) NOT NULL UNIQUE,
            descripcion TEXT,
            icono VARCHAR(50),
            activo TINYINT DEFAULT 1,
            orden INT DEFAULT 0,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Tabla de productos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_productos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            codigo VARCHAR(50) NOT NULL UNIQUE,
            nombre VARCHAR(255) NOT NULL,
            descripcion TEXT,
            categoria_id INT NOT NULL,
            precio_base DECIMAL(10, 2) NOT NULL,
            tipo_precio ENUM('fijo', 'variable') DEFAULT 'fijo',
            imagen VARCHAR(255),
            stock INT DEFAULT 0,
            mostrar_ecommerce TINYINT DEFAULT 1,
            activo TINYINT DEFAULT 1,
            orden INT DEFAULT 0,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (categoria_id) REFERENCES ecommerce_categorias(id) ON DELETE RESTRICT,
            INDEX idx_categoria (categoria_id),
            INDEX idx_activo (activo)
        )
    ");

    // Tabla de matriz de precios (para productos como cortinas/toldos)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_matriz_precios (
            id INT PRIMARY KEY AUTO_INCREMENT,
            producto_id INT NOT NULL,
            alto_cm INT NOT NULL,
            ancho_cm INT NOT NULL,
            precio DECIMAL(10, 2) NOT NULL,
            stock INT DEFAULT 0,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (producto_id) REFERENCES ecommerce_productos(id) ON DELETE CASCADE,
            UNIQUE KEY unique_producto_medidas (producto_id, alto_cm, ancho_cm),
            INDEX idx_producto (producto_id)
        )
    ");

    // Tabla de clientes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_clientes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255),
            nombre VARCHAR(255) NOT NULL,
            telefono VARCHAR(20),
            provincia VARCHAR(100),
            localidad VARCHAR(100),
            ciudad VARCHAR(100),
            direccion VARCHAR(255),
            codigo_postal VARCHAR(10),
            responsabilidad_fiscal VARCHAR(100),
            documento_tipo VARCHAR(10),
            documento_numero VARCHAR(20),
            email_verificado TINYINT DEFAULT 0,
            email_verificado_en DATETIME,
            email_verificacion_token VARCHAR(64),
            email_verificacion_expira DATETIME,
            activo TINYINT DEFAULT 1,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_verificacion_token (email_verificacion_token)
        )
    ");

    // Agregar columnas nuevas y de verificación de email si la tabla ya existía
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_clientes LIKE 'localidad'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_clientes ADD COLUMN localidad VARCHAR(100) AFTER provincia");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_clientes LIKE 'responsabilidad_fiscal'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_clientes ADD COLUMN responsabilidad_fiscal VARCHAR(100) AFTER codigo_postal");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_clientes LIKE 'documento_tipo'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_clientes ADD COLUMN documento_tipo VARCHAR(10) AFTER responsabilidad_fiscal");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_clientes LIKE 'documento_numero'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_clientes ADD COLUMN documento_numero VARCHAR(20) AFTER documento_tipo");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_clientes LIKE 'email_verificado'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_clientes ADD COLUMN email_verificado TINYINT DEFAULT 0 AFTER codigo_postal");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_clientes LIKE 'email_verificado_en'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_clientes ADD COLUMN email_verificado_en DATETIME AFTER email_verificado");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_clientes LIKE 'email_verificacion_token'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_clientes ADD COLUMN email_verificacion_token VARCHAR(64) AFTER email_verificado_en");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_clientes LIKE 'email_verificacion_expira'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_clientes ADD COLUMN email_verificacion_expira DATETIME AFTER email_verificacion_token");
    }
    $idx = $pdo->query("SHOW INDEX FROM ecommerce_clientes WHERE Key_name = 'idx_verificacion_token'");
    if ($idx->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_clientes ADD INDEX idx_verificacion_token (email_verificacion_token)");
    }

    // Marcar como verificados a clientes existentes sin token
    $pdo->exec("UPDATE ecommerce_clientes SET email_verificado = 1, email_verificado_en = COALESCE(email_verificado_en, NOW()) WHERE email_verificado = 0 AND (email_verificacion_token IS NULL OR email_verificacion_token = '')");

    // Tabla de pedidos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_pedidos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            numero_pedido VARCHAR(50) NOT NULL UNIQUE,
            cliente_id INT NOT NULL,
            subtotal DECIMAL(10, 2) NULL,
            envio DECIMAL(10, 2) NULL,
            descuento_monto DECIMAL(10, 2) NULL,
            codigo_descuento VARCHAR(50) NULL,
            total DECIMAL(10, 2) NOT NULL,
            factura_a TINYINT DEFAULT 0,
            envio_nombre VARCHAR(255),
            envio_telefono VARCHAR(20),
            envio_direccion VARCHAR(255),
            envio_localidad VARCHAR(100),
            envio_provincia VARCHAR(100),
            envio_codigo_postal VARCHAR(10),
            estado ENUM('pendiente_pago', 'esperando_transferencia', 'esperando_envio', 'pagado', 'pago_pendiente', 'pago_autorizado', 'pago_en_proceso', 'pago_rechazado', 'pago_reembolsado', 'confirmado', 'preparando', 'enviado', 'entregado', 'cancelado') DEFAULT 'pendiente_pago',
            metodo_pago VARCHAR(100),
            observaciones TEXT,
            mercadopago_preference_id VARCHAR(255),
            mercadopago_payment_id VARCHAR(255),
            mercadopago_status VARCHAR(50),
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_envio DATETIME,
            fecha_entrega DATETIME,
            FOREIGN KEY (cliente_id) REFERENCES ecommerce_clientes(id) ON DELETE RESTRICT,
            INDEX idx_estado (estado),
            INDEX idx_cliente (cliente_id),
            INDEX idx_fecha (fecha_creacion),
            INDEX idx_mp_payment (mercadopago_payment_id)
        )
    ");

    // Asegurar columnas de Mercado Pago si la tabla ya existía
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos LIKE 'mercadopago_preference_id'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD COLUMN mercadopago_preference_id VARCHAR(255) AFTER observaciones");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos LIKE 'mercadopago_payment_id'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD COLUMN mercadopago_payment_id VARCHAR(255) AFTER mercadopago_preference_id");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos LIKE 'mercadopago_status'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD COLUMN mercadopago_status VARCHAR(50) AFTER mercadopago_payment_id");
    }
    $idx = $pdo->query("SHOW INDEX FROM ecommerce_pedidos WHERE Key_name = 'idx_mp_payment'");
    if ($idx->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD INDEX idx_mp_payment (mercadopago_payment_id)");
    }

    // Asegurar columnas de descuento/envío si la tabla ya existía
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos LIKE 'subtotal'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD COLUMN subtotal DECIMAL(10,2) NULL AFTER cliente_id");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos LIKE 'envio'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD COLUMN envio DECIMAL(10,2) NULL AFTER subtotal");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos LIKE 'descuento_monto'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD COLUMN descuento_monto DECIMAL(10,2) NULL AFTER envio");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos LIKE 'codigo_descuento'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD COLUMN codigo_descuento VARCHAR(50) NULL AFTER descuento_monto");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos LIKE 'factura_a'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD COLUMN factura_a TINYINT DEFAULT 0 AFTER total");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos LIKE 'envio_nombre'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD COLUMN envio_nombre VARCHAR(255) AFTER factura_a");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos LIKE 'envio_telefono'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD COLUMN envio_telefono VARCHAR(20) AFTER envio_nombre");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos LIKE 'envio_direccion'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD COLUMN envio_direccion VARCHAR(255) AFTER envio_telefono");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos LIKE 'envio_localidad'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD COLUMN envio_localidad VARCHAR(100) AFTER envio_direccion");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos LIKE 'envio_provincia'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD COLUMN envio_provincia VARCHAR(100) AFTER envio_localidad");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos LIKE 'envio_codigo_postal'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD COLUMN envio_codigo_postal VARCHAR(10) AFTER envio_provincia");
    }

    // Tabla de items del pedido
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_pedido_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            pedido_id INT NOT NULL,
            producto_id INT NOT NULL,
            cantidad INT NOT NULL,
            precio_unitario DECIMAL(10, 2) NOT NULL,
            alto_cm INT,
            ancho_cm INT,
            subtotal DECIMAL(10, 2) NOT NULL,
            FOREIGN KEY (pedido_id) REFERENCES ecommerce_pedidos(id) ON DELETE CASCADE,
            FOREIGN KEY (producto_id) REFERENCES ecommerce_productos(id) ON DELETE RESTRICT,
            INDEX idx_pedido (pedido_id)
        )
    ");

    // Tabla de información de la empresa
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_empresa (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(255) NOT NULL,
            descripcion TEXT,
            logo VARCHAR(255),
            email VARCHAR(255),
            telefono VARCHAR(20),
            direccion TEXT,
            ciudad VARCHAR(100),
            provincia VARCHAR(100),
            pais VARCHAR(100),
            horario_atencion TEXT,
            redes_sociales JSON,
            about_us TEXT,
            terminos_condiciones TEXT,
            politica_privacidad TEXT,
            cuit VARCHAR(50),
            responsabilidad_fiscal VARCHAR(100),
            iibb VARCHAR(50),
            regimen_iva VARCHAR(100),
            fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Agregar columnas fiscales si la tabla ya existía
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_empresa LIKE 'cuit'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_empresa ADD COLUMN cuit VARCHAR(50) AFTER politica_privacidad");
        $pdo->exec("ALTER TABLE ecommerce_empresa ADD COLUMN responsabilidad_fiscal VARCHAR(100) AFTER cuit");
        $pdo->exec("ALTER TABLE ecommerce_empresa ADD COLUMN iibb VARCHAR(50) AFTER responsabilidad_fiscal");
        $pdo->exec("ALTER TABLE ecommerce_empresa ADD COLUMN regimen_iva VARCHAR(100) AFTER iibb");
    }

    // Configuración general del ecommerce
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_config (
            id INT PRIMARY KEY AUTO_INCREMENT,
            lista_precio_id INT,
            fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("INSERT INTO ecommerce_config (id, lista_precio_id) VALUES (1, NULL) ON DUPLICATE KEY UPDATE id = id");

    // Configuración de correo (SMTP)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_email_config (
            id INT PRIMARY KEY AUTO_INCREMENT,
            from_email VARCHAR(255),
            from_name VARCHAR(255),
            smtp_host VARCHAR(255),
            smtp_port INT,
            smtp_user VARCHAR(255),
            smtp_pass VARCHAR(255),
            smtp_secure VARCHAR(20),
            smtp_auth TINYINT DEFAULT 1,
            activo TINYINT DEFAULT 1,
            fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_activo (activo)
        )
    ");

    $pdo->exec("INSERT INTO ecommerce_email_config (id, activo) VALUES (1, 1) ON DUPLICATE KEY UPDATE id = id");

    // Configuración de envío
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_envio_config (
            id INT PRIMARY KEY AUTO_INCREMENT,
            costo_base DECIMAL(10, 2) NOT NULL DEFAULT 500.00,
            gratis_desde_importe DECIMAL(10, 2) NULL,
            gratis_desde_cantidad INT NULL,
            activo TINYINT DEFAULT 1,
            fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("INSERT INTO ecommerce_envio_config (id, costo_base, activo) VALUES (1, 500.00, 1) ON DUPLICATE KEY UPDATE id = id");

    // Preguntas frecuentes (FAQ)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_faq (
            id INT PRIMARY KEY AUTO_INCREMENT,
            pregunta VARCHAR(255) NOT NULL,
            respuesta TEXT NOT NULL,
            activo TINYINT DEFAULT 1,
            orden INT DEFAULT 0,
            fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Configuración de métodos de pago
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_metodos_pago (
            id INT PRIMARY KEY AUTO_INCREMENT,
            codigo VARCHAR(50) NOT NULL UNIQUE,
            nombre VARCHAR(100) NOT NULL,
            tipo ENUM('manual','mercadopago') NOT NULL DEFAULT 'manual',
            instrucciones_html TEXT NULL,
            activo TINYINT DEFAULT 1,
            orden INT DEFAULT 0,
            fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_activo (activo)
        )
    ");

    // Métodos de pago iniciales
    $pdo->exec("INSERT INTO ecommerce_metodos_pago (codigo, nombre, tipo, instrucciones_html, activo, orden)
        SELECT 'transferencia_bancaria', 'Transferencia Bancaria', 'manual',
        '<p><strong>Datos para transferencia:</strong></p><ul><li>Banco: Banco Ejemplo</li><li>CBU: 0000000000000000000000</li><li>Alias: TUCU.ROLLER</li><li>Titular: Tucu Roller</li></ul><p>Luego de transferir, envíanos el comprobante.</p>',
        1, 1
        WHERE NOT EXISTS (SELECT 1 FROM ecommerce_metodos_pago WHERE codigo = 'transferencia_bancaria')
    ");
    $pdo->exec("INSERT INTO ecommerce_metodos_pago (codigo, nombre, tipo, instrucciones_html, activo, orden)
        SELECT 'mercadopago_tarjeta', 'Tarjeta de Crédito (Mercado Pago)', 'mercadopago',
        '<p>Serás redirigido a Mercado Pago para completar el pago con tarjeta.</p>',
        1, 2
        WHERE NOT EXISTS (SELECT 1 FROM ecommerce_metodos_pago WHERE codigo = 'mercadopago_tarjeta')
    ");
    $pdo->exec("INSERT INTO ecommerce_metodos_pago (codigo, nombre, tipo, instrucciones_html, activo, orden)
        SELECT 'efectivo_entrega', 'Efectivo contra Entrega', 'manual',
        '<p>Pagás en efectivo al recibir tu pedido.</p>',
        1, 3
        WHERE NOT EXISTS (SELECT 1 FROM ecommerce_metodos_pago WHERE codigo = 'efectivo_entrega')
    ");

    // Configuración de códigos de descuento
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_descuentos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            codigo VARCHAR(50) NOT NULL UNIQUE,
            tipo ENUM('porcentaje','monto') NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            minimo_subtotal DECIMAL(10,2) NULL,
            fecha_inicio DATE NULL,
            fecha_fin DATE NULL,
            usos_max INT NULL,
            usos_usados INT DEFAULT 0,
            activo TINYINT DEFAULT 1,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_activo (activo),
            INDEX idx_codigo (codigo)
        )
    ");

    // Tabla de proveedores
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_proveedores (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            telefono VARCHAR(50),
            direccion VARCHAR(255),
            cuit VARCHAR(50),
            activo TINYINT DEFAULT 1,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Tabla de compras
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_compras (
            id INT PRIMARY KEY AUTO_INCREMENT,
            numero_compra VARCHAR(50) NOT NULL UNIQUE,
            proveedor_id INT NOT NULL,
            fecha_compra DATE NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            observaciones TEXT,
            creado_por INT,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (proveedor_id) REFERENCES ecommerce_proveedores(id) ON DELETE RESTRICT,
            INDEX idx_proveedor (proveedor_id),
            INDEX idx_fecha (fecha_compra)
        )
    ");

    // Items de compra
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_compra_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            compra_id INT NOT NULL,
            producto_id INT NOT NULL,
            cantidad INT NOT NULL,
            costo_unitario DECIMAL(10,2) NOT NULL,
            alto_cm INT,
            ancho_cm INT,
            subtotal DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (compra_id) REFERENCES ecommerce_compras(id) ON DELETE CASCADE,
            FOREIGN KEY (producto_id) REFERENCES ecommerce_productos(id) ON DELETE RESTRICT,
            INDEX idx_compra (compra_id)
        )
    ");

    // Movimientos de inventario
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_inventario_movimientos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            producto_id INT NOT NULL,
            tipo ENUM('compra','ajuste','venta') NOT NULL,
            cantidad INT NOT NULL,
            alto_cm INT,
            ancho_cm INT,
            referencia VARCHAR(100),
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (producto_id) REFERENCES ecommerce_productos(id) ON DELETE RESTRICT,
            INDEX idx_producto (producto_id)
        )
    ");

    echo "✓ Tablas del ecommerce creadas correctamente";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
