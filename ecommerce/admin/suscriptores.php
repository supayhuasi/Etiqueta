<?php
require 'includes/header.php';

// Obtener todos los suscriptores
$stmt = $pdo->query("SELECT * FROM ecommerce_suscriptores ORDER BY fecha_creacion DESC");
$suscriptores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar total
$total_suscriptores = count($suscriptores);

// Acción de eliminar
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    try {
        $stmt = $pdo->prepare("DELETE FROM ecommerce_suscriptores WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: suscriptores.php?success=Suscriptor eliminado");
        exit;
    } catch (Exception $e) {
        $error = "Error al eliminar: " . $e->getMessage();
    }
}

// Acción de exportar a CSV
if (isset($_GET['exportar'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="suscriptores_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Email', 'Fecha de Suscripción']);
    
    foreach ($suscriptores as $sus) {
        fputcsv($output, [
            $sus['email'],
            date('d/m/Y H:i', strtotime($sus['fecha_creacion']))
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>📧 Suscriptores Newsletter</h1>
        <p class="text-muted">Total: <?= $total_suscriptores ?> suscriptor<?= $total_suscriptores != 1 ? 'es' : '' ?></p>
    </div>
    <div>
        <a href="?exportar=1" class="btn btn-success">📥 Exportar CSV</a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5>Lista de Suscriptores</h5>
    </div>
    <div class="card-body">
        <?php if (empty($suscriptores)): ?>
            <div class="alert alert-info">
                No hay suscriptores registrados aún.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Email</th>
                            <th width="180">Fecha de Suscripción</th>
                            <th width="100">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suscriptores as $key => $sus): ?>
                            <tr>
                                <td><?= $key + 1 ?></td>
                                <td>
                                    <a href="mailto:<?= htmlspecialchars($sus['email']) ?>">
                                        <?= htmlspecialchars($sus['email']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?= date('d/m/Y H:i', strtotime($sus['fecha_creacion'])) ?>
                                </td>
                                <td>
                                    <a href="?eliminar=<?= $sus['id'] ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('¿Eliminar este suscriptor?')">
                                        🗑️
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h5>💡 Información</h5>
    </div>
    <div class="card-body">
        <h6>¿Dónde se recopilan estos emails?</h6>
        <ul class="mb-3">
            <li>Popup de suscripción en la página de inicio</li>
            <li>Se muestra automáticamente después de unos segundos</li>
            <li>Los emails se guardan en la tabla <code>ecommerce_suscriptores</code></li>
        </ul>

        <h6>¿Cómo usar esta lista?</h6>
        <ul class="mb-0">
            <li>Exportá el CSV y usalo en tu plataforma de email marketing (MailChimp, SendGrid, etc.)</li>
            <li>Podés enviar newsletters manualmente copiando los emails</li>
            <li>Los suscriptores duplicados se ignoran automáticamente</li>
        </ul>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
