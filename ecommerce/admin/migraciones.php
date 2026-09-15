<?php
require 'includes/header.php';

$mensaje = '';
$error = '';

// Ejecutar migración si se solicita
if ($_POST['ejecutar_migracion'] ?? false) {
    try {
        echo "Iniciando migración de opciones de atributos con imágenes...\n<br>";
        
        // 1. Crear tabla para opciones de atributos con imágenes
        echo "1. Creando tabla ecommerce_atributo_opciones...\n<br>";
        $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_atributo_opciones'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("
                CREATE TABLE ecommerce_atributo_opciones (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    atributo_id INT NOT NULL,
                    nombre VARCHAR(100) NOT NULL,
                    imagen VARCHAR(255),
                    color VARCHAR(7),
                    costo_adicional DECIMAL(10,2) DEFAULT 0,
                    stock DECIMAL(10,2) DEFAULT 0,
                    orden INT DEFAULT 0,
                    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (atributo_id) REFERENCES ecommerce_producto_atributos(id) ON DELETE CASCADE,
                    INDEX (atributo_id)
                )
            ");
            $mensaje = "✓ Tabla ecommerce_atributo_opciones creada exitosamente<br>";
        } else {
            $mensaje = "✓ La tabla ecommerce_atributo_opciones ya existe<br>";
        }
        
        // 2. Crear directorio para uploads
        echo "2. Creando directorio para uploads...\n<br>";
        $dir = '../../uploads/atributos/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            $mensaje .= "✓ Directorio creado: {$dir}<br>";
        } else {
            $mensaje .= "✓ Directorio ya existe: {$dir}<br>";
        }
        
        $mensaje .= "<br><strong>✓ Migración completada exitosamente</strong>";
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card mt-5">
            <div class="card-header bg-warning text-dark">
                <h4>🔧 Migración - Opciones de Atributos con Imágenes</h4>
            </div>
            <div class="card-body">
                <?php if ($mensaje): ?>
                    <div class="alert alert-success">
                        <?= $mensaje ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <p class="text-muted">
                    Esta migración crea la tabla <code>ecommerce_atributo_opciones</code> necesaria para 
                    almacenar opciones de atributos seleccionables con imágenes.
                </p>

                <div class="alert alert-info">
                    <h6>Cambios que realiza:</h6>
                    <ul class="mb-0">
                        <li>Crea tabla <code>ecommerce_atributo_opciones</code> con campos: id, atributo_id, nombre, imagen, color, costo_adicional, stock, orden</li>
                        <li>Crea directorio <code>/uploads/atributos/</code> para guardar imágenes</li>
                        <li>Establece relación con tabla <code>ecommerce_producto_atributos</code></li>
                    </ul>
                </div>

                <form method="POST" style="margin-top: 20px;">
                    <button type="submit" name="ejecutar_migracion" value="1" class="btn btn-lg btn-success">
                        ▶️ Ejecutar Migración
                    </button>
                    <a href="index.php" class="btn btn-lg btn-secondary">← Volver</a>
                </form>

                <hr class="mt-4">

                <h6 class="mt-4">📋 Información Técnica:</h6>
                <pre style="background-color: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;">
CREATE TABLE ecommerce_atributo_opciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    atributo_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    imagen VARCHAR(255),
    orden INT DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (atributo_id) REFERENCES ecommerce_producto_atributos(id) ON DELETE CASCADE,
    INDEX (atributo_id)
)</pre>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
