<?php
require_once __DIR__ . '/../../config.php';
header('Content-Type: text/plain; charset=utf-8');

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit("Error: no se pudo inicializar la conexión a la base de datos.\n");
}

try {
    // Tabla de tipos (Seguro, VTV/RTO, Permiso de circulación, etc.)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tipos_seguros_permisos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(100) NOT NULL UNIQUE,
            descripcion TEXT,
            color VARCHAR(20),
            activo BOOLEAN DEFAULT 1,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Tabla principal de seguros y permisos por vehículo
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS seguros_permisos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            vehiculo_patente VARCHAR(20) NOT NULL,
            vehiculo_descripcion VARCHAR(150),
            tipo_id INT NOT NULL,
            numero VARCHAR(100),
            entidad VARCHAR(150),
            fecha_emision DATE NULL,
            fecha_vencimiento DATE NOT NULL,
            costo DECIMAL(12,2) NULL,
            archivo VARCHAR(255),
            observaciones TEXT,
            usuario_registra INT NOT NULL,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tipo_id) REFERENCES tipos_seguros_permisos(id),
            FOREIGN KEY (usuario_registra) REFERENCES usuarios(id),
            INDEX idx_vencimiento (fecha_vencimiento),
            INDEX idx_tipo (tipo_id),
            INDEX idx_patente (vehiculo_patente)
        )
    ");

    // Tipos predeterminados
    $tipos = [
        ['Seguro', 'Póliza de seguro del vehículo', '#0d6efd'],
        ['VTV / RTO', 'Verificación técnica vehicular / Revisión técnica obligatoria', '#28A745'],
        ['Permiso de circulación', 'Permiso o patente municipal/provincial de circulación', '#FFC107'],
        ['Cédula verde/azul', 'Cédula de identificación del automotor', '#6F42C1'],
        ['Otro', 'Otro documento con vigencia', '#6C757D']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO tipos_seguros_permisos (nombre, descripcion, color) VALUES (?, ?, ?)");
    foreach ($tipos as $tipo) {
        $stmt->execute($tipo);
    }

    echo "✓ Tablas de seguros y permisos creadas correctamente\n";
    echo "✓ Tipos predeterminados insertados\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error al configurar seguros y permisos: " . $e->getMessage() . "\n";
}
