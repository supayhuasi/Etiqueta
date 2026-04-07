<?php
require 'config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_cotizacion_clientes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(255) NOT NULL,
            email VARCHAR(255) NULL,
            email_normalizado VARCHAR(191) NULL,
            telefono VARCHAR(50) NULL,
            telefono_normalizado VARCHAR(50) NULL,
            direccion VARCHAR(255) NULL,
            empresa VARCHAR(255) NULL,
            cuit VARCHAR(20) NULL,
            cuit_normalizado VARCHAR(20) NULL,
            factura_a TINYINT(1) NOT NULL DEFAULT 0,
            es_empresa TINYINT(1) NOT NULL DEFAULT 0,
            activo TINYINT DEFAULT 1,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_cot_cli_email_norm (email_normalizado),
            UNIQUE KEY uniq_cot_cli_tel_norm (telefono_normalizado),
            UNIQUE KEY uniq_cot_cli_cuit_norm (cuit_normalizado),
            INDEX idx_nombre (nombre)
        )
    ");

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones LIKE 'cliente_id'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN cliente_id INT NULL AFTER empresa");
        echo "✓ Columna cliente_id agregada en ecommerce_cotizaciones<br>";
    }

    $stmt = $pdo->query("SHOW INDEX FROM ecommerce_cotizaciones WHERE Key_name = 'idx_cliente' ");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD INDEX idx_cliente (cliente_id)");
        echo "✓ Índice idx_cliente agregado<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes LIKE 'direccion'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN direccion VARCHAR(255) NULL AFTER telefono");
        echo "✓ Columna direccion agregada en ecommerce_cotizacion_clientes<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes LIKE 'cuit'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN cuit VARCHAR(20) NULL AFTER direccion");
        echo "✓ Columna cuit agregada en ecommerce_cotizacion_clientes<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes LIKE 'factura_a'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN factura_a TINYINT(1) NOT NULL DEFAULT 0 AFTER cuit");
        echo "✓ Columna factura_a agregada en ecommerce_cotizacion_clientes<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes LIKE 'es_empresa'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN es_empresa TINYINT(1) NOT NULL DEFAULT 0 AFTER factura_a");
        echo "✓ Columna es_empresa agregada en ecommerce_cotizacion_clientes<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes LIKE 'email_normalizado'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN email_normalizado VARCHAR(191) NULL AFTER email");
        echo "✓ Columna email_normalizado agregada en ecommerce_cotizacion_clientes<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes LIKE 'telefono_normalizado'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN telefono_normalizado VARCHAR(50) NULL AFTER telefono");
        echo "✓ Columna telefono_normalizado agregada en ecommerce_cotizacion_clientes<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes LIKE 'cuit_normalizado'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN cuit_normalizado VARCHAR(20) NULL AFTER cuit");
        echo "✓ Columna cuit_normalizado agregada en ecommerce_cotizacion_clientes<br>";
    }

    $rows = $pdo->query("SELECT id, email, telefono, cuit FROM ecommerce_cotizacion_clientes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $emails = [];
    $telefonos = [];
    $cuits = [];
    $update = $pdo->prepare("UPDATE ecommerce_cotizacion_clientes SET email_normalizado = ?, telefono_normalizado = ?, cuit_normalizado = ? WHERE id = ?");
    foreach ($rows as $row) {
        $emailNorm = !empty($row['email']) ? strtolower(trim((string)$row['email'])) : '';
        if ($emailNorm !== '' && isset($emails[$emailNorm])) {
            $emailNorm = '';
        }
        if ($emailNorm !== '') {
            $emails[$emailNorm] = true;
        }

        $telefonoNorm = !empty($row['telefono']) ? preg_replace('/\D+/', '', (string)$row['telefono']) : '';
        if ($telefonoNorm !== '' && isset($telefonos[$telefonoNorm])) {
            $telefonoNorm = '';
        }
        if ($telefonoNorm !== '') {
            $telefonos[$telefonoNorm] = true;
        }

        $cuitNorm = !empty($row['cuit']) ? preg_replace('/\D+/', '', (string)$row['cuit']) : '';
        if ($cuitNorm !== '' && isset($cuits[$cuitNorm])) {
            $cuitNorm = '';
        }
        if ($cuitNorm !== '') {
            $cuits[$cuitNorm] = true;
        }

        $update->execute([
            $emailNorm !== '' ? $emailNorm : null,
            $telefonoNorm !== '' ? $telefonoNorm : null,
            $cuitNorm !== '' ? $cuitNorm : null,
            (int)$row['id']
        ]);
    }

    $idx = $pdo->query("SHOW INDEX FROM ecommerce_cotizacion_clientes WHERE Key_name = 'uniq_cot_cli_email_norm'");
    if ($idx->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD UNIQUE INDEX uniq_cot_cli_email_norm (email_normalizado)");
        echo "✓ Índice único de email normalizado agregado<br>";
    }

    $idx = $pdo->query("SHOW INDEX FROM ecommerce_cotizacion_clientes WHERE Key_name = 'uniq_cot_cli_tel_norm'");
    if ($idx->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD UNIQUE INDEX uniq_cot_cli_tel_norm (telefono_normalizado)");
        echo "✓ Índice único de teléfono normalizado agregado<br>";
    }

    $idx = $pdo->query("SHOW INDEX FROM ecommerce_cotizacion_clientes WHERE Key_name = 'uniq_cot_cli_cuit_norm'");
    if ($idx->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD UNIQUE INDEX uniq_cot_cli_cuit_norm (cuit_normalizado)");
        echo "✓ Índice único de CUIT normalizado agregado<br>";
    }

    try {
        $pdo->exec("DROP TRIGGER IF EXISTS ecommerce_cot_cli_bi");
        $pdo->exec("CREATE TRIGGER ecommerce_cot_cli_bi BEFORE INSERT ON ecommerce_cotizacion_clientes FOR EACH ROW SET
            NEW.email_normalizado = NULLIF(LOWER(TRIM(COALESCE(NEW.email, ''))), ''),
            NEW.telefono_normalizado = NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(NEW.telefono, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', ''), ''),
            NEW.cuit_normalizado = NULLIF(REPLACE(REPLACE(COALESCE(NEW.cuit, ''), '-', ''), ' ', ''), '')");
        echo "✓ Trigger de inserción anti-duplicados creado<br>";
    } catch (Exception $e) {
        echo "ℹ No se pudo crear trigger de inserción: " . htmlspecialchars($e->getMessage()) . "<br>";
    }

    try {
        $pdo->exec("DROP TRIGGER IF EXISTS ecommerce_cot_cli_bu");
        $pdo->exec("CREATE TRIGGER ecommerce_cot_cli_bu BEFORE UPDATE ON ecommerce_cotizacion_clientes FOR EACH ROW SET
            NEW.email_normalizado = NULLIF(LOWER(TRIM(COALESCE(NEW.email, ''))), ''),
            NEW.telefono_normalizado = NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(NEW.telefono, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', ''), ''),
            NEW.cuit_normalizado = NULLIF(REPLACE(REPLACE(COALESCE(NEW.cuit, ''), '-', ''), ' ', ''), '')");
        echo "✓ Trigger de actualización anti-duplicados creado<br>";
    } catch (Exception $e) {
        echo "ℹ No se pudo crear trigger de actualización: " . htmlspecialchars($e->getMessage()) . "<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones LIKE 'cuit'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN cuit VARCHAR(20) NULL AFTER telefono");
        echo "✓ Columna cuit agregada en ecommerce_cotizaciones<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones LIKE 'factura_a'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN factura_a TINYINT(1) NOT NULL DEFAULT 0 AFTER cuit");
        echo "✓ Columna factura_a agregada en ecommerce_cotizaciones<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones LIKE 'es_empresa'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN es_empresa TINYINT(1) NOT NULL DEFAULT 0 AFTER factura_a");
        echo "✓ Columna es_empresa agregada en ecommerce_cotizaciones<br>";
    }

    echo "✓ Setup de clientes para cotizaciones completado";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
