<?php
require 'includes/header.php';

$stmt = $pdo->prepare("SELECT * FROM ecommerce_listas_precios WHERE id = ?");
$stmt->execute([$_GET['id'] ?? 0]);
$lista = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lista) {
    die("<div class='alert alert-danger'>Lista de precios no encontrada</div>");
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8">
            <h1>Editar Lista de Precios</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="listas_precios.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <form method="POST" action="listas_precios_crear.php?id=<?= $lista['id'] ?>">
                <div class="mb-3">
                    <label class="form-label">Nombre de la Lista</label>
                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($lista['nombre']) ?>" placeholder="Ej: Mayorista, Distribuidor, etc." required>
                    <small class="text-muted">Este nombre debe ser único</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3" placeholder="Describe para quién es esta lista de precios"><?= htmlspecialchars($lista['descripcion'] ?? '') ?></textarea>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= $lista['activo'] ? 'checked' : '' ?>>
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

                <button type="submit" class="btn btn-primary">💾 Actualizar</button>
                <a href="listas_precios.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
