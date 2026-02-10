<?php
require 'includes/header.php';

function asegurar_tablas_encuestas(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_encuestas (
        id INT PRIMARY KEY AUTO_INCREMENT,
        titulo VARCHAR(255) NOT NULL,
        descripcion TEXT,
        fecha_entrega DATE NOT NULL,
        token_publico VARCHAR(64) NOT NULL UNIQUE,
        activo TINYINT(1) DEFAULT 1,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_encuesta_preguntas (
        id INT PRIMARY KEY AUTO_INCREMENT,
        encuesta_id INT NOT NULL,
        pregunta VARCHAR(255) NOT NULL,
        tipo ENUM('texto','opcion','escala') DEFAULT 'texto',
        opciones_json TEXT NULL,
        orden INT DEFAULT 0,
        FOREIGN KEY (encuesta_id) REFERENCES ecommerce_encuestas(id) ON DELETE CASCADE,
        INDEX idx_encuesta (encuesta_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_encuesta_respuestas (
        id INT PRIMARY KEY AUTO_INCREMENT,
        encuesta_id INT NOT NULL,
        pregunta_id INT NOT NULL,
        respuesta TEXT,
        email VARCHAR(255) NULL,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (encuesta_id) REFERENCES ecommerce_encuestas(id) ON DELETE CASCADE,
        FOREIGN KEY (pregunta_id) REFERENCES ecommerce_encuesta_preguntas(id) ON DELETE CASCADE,
        INDEX idx_encuesta (encuesta_id)
    )");
}

asegurar_tablas_encuestas($pdo);

$stmt = $pdo->query("SELECT e.*, (SELECT COUNT(*) FROM ecommerce_encuesta_respuestas r WHERE r.encuesta_id = e.id) as respuestas FROM ecommerce_encuestas e ORDER BY e.fecha_creacion DESC");
$encuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>📝 Encuestas</h1>
        <p class="text-muted">Crear y compartir encuestas</p>
    </div>
    <a href="encuestas_crear.php" class="btn btn-primary">+ Nueva Encuesta</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Título</th>
                    <th>Entrega</th>
                    <th>Respuestas</th>
                    <th>Estado</th>
                    <th>Link público</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($encuestas)): ?>
                    <tr><td colspan="6" class="text-center text-muted">Sin encuestas</td></tr>
                <?php else: ?>
                    <?php foreach ($encuestas as $encuesta): ?>
                        <?php
                        $link_publico = '../encuesta.php?token=' . urlencode($encuesta['token_publico']);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($encuesta['titulo']) ?></td>
                            <td><?= htmlspecialchars($encuesta['fecha_entrega']) ?></td>
                            <td><?= (int)$encuesta['respuestas'] ?></td>
                            <td>
                                <span class="badge bg-<?= $encuesta['activo'] ? 'success' : 'secondary' ?>">
                                    <?= $encuesta['activo'] ? 'Activa' : 'Inactiva' ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= htmlspecialchars($link_publico) ?>" target="_blank">Abrir</a>
                            </td>
                            <td>
                                <a href="encuestas_editar.php?id=<?= $encuesta['id'] ?>" class="btn btn-sm btn-primary">✏️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
