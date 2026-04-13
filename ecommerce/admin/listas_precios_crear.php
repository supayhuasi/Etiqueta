<?php
require 'includes/header.php';

try {
    $cols = $pdo->query("SHOW COLUMNS FROM ecommerce_listas_precios")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('mostrar_en_cotizacion_pdf', $cols, true)) {
        $pdo->exec("ALTER TABLE ecommerce_listas_precios ADD COLUMN mostrar_en_cotizacion_pdf TINYINT(1) NOT NULL DEFAULT 0 AFTER activo");
    }
    if (!in_array('cantidad_cuotas', $cols, true)) {
        $pdo->exec("ALTER TABLE ecommerce_listas_precios ADD COLUMN cantidad_cuotas INT NOT NULL DEFAULT 1 AFTER mostrar_en_cotizacion_pdf");
    }
} catch (Exception $e) {
    // Si la migracion falla, el formulario sigue funcionando con valores por defecto.
}

// Si llega ID, es edición
$editar = false;
$lista = null;

if (isset($_GET['id'])) {
    $editar = true;
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_listas_precios WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $lista = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lista) {
        die("<div class='alert alert-danger'>Lista de precios no encontrada</div>");
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $activo = isset($_POST['activo']) ? 1 : 0;
    $mostrar_en_cotizacion_pdf = isset($_POST['mostrar_en_cotizacion_pdf']) ? 1 : 0;
    $cantidad_cuotas = max(1, intval($_POST['cantidad_cuotas'] ?? 1));

    // Validar nombre único
    if (!$editar) {
        $stmt = $pdo->prepare("SELECT id FROM ecommerce_listas_precios WHERE nombre = ?");
        $stmt->execute([$nombre]);
        if ($stmt->fetch()) {
            die("<div class='alert alert-danger'>Ya existe una lista con este nombre</div>");
        }
    }

    try {
        if ($editar) {
            $stmt = $pdo->prepare("
                UPDATE ecommerce_listas_precios 
                SET nombre = ?, descripcion = ?, activo = ?, mostrar_en_cotizacion_pdf = ?, cantidad_cuotas = ?
                WHERE id = ?
            ");
            $stmt->execute([$nombre, $descripcion, $activo, $mostrar_en_cotizacion_pdf, $cantidad_cuotas, $_GET['id']]);
            $mensaje = "✓ Lista de precios actualizada correctamente";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_listas_precios (nombre, descripcion, activo, mostrar_en_cotizacion_pdf, cantidad_cuotas)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nombre, $descripcion, $activo, $mostrar_en_cotizacion_pdf, $cantidad_cuotas]);
            $mensaje = "✓ Lista de precios creada correctamente";
        }
        echo "<div class='alert alert-success'>$mensaje</div>";
        header("refresh:2; url=listas_precios.php");
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8">
            <h1><?= $editar ? '✏️ Editar Lista de Precios' : '💰 Crear Lista de Precios' ?></h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="listas_precios.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Nombre de la Lista</label>
                    <input type="text" name="nombre" class="form-control" value="<?= $lista['nombre'] ?? '' ?>" placeholder="Ej: Mayorista, Distribuidor, etc." required>
                    <small class="text-muted">Este nombre debe ser único</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3" placeholder="Describe para quién es esta lista de precios"><?= $lista['descripcion'] ?? '' ?></textarea>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= ($lista['activo'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activo">
                        Activa
                    </label>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="mostrar_en_cotizacion_pdf" class="form-check-input" id="mostrar_en_cotizacion_pdf" <?= !empty($lista['mostrar_en_cotizacion_pdf']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="mostrar_en_cotizacion_pdf">
                        Mostrar esta lista en el PDF de cotizacion
                    </label>
                </div>

                <div class="mb-3">
                    <label class="form-label">Cantidad de cuotas</label>
                    <input type="number" name="cantidad_cuotas" class="form-control" min="1" step="1" value="<?= max(1, intval($lista['cantidad_cuotas'] ?? 1)) ?>">
                    <small class="text-muted">Se usara para calcular el importe de cada cuota en el PDF de cotizacion.</small>
                </div>

                <button type="submit" class="btn btn-primary">💾 Guardar</button>
                <a href="listas_precios.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
