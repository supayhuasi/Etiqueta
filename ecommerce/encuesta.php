<?php
require 'config.php';
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

$token = trim($_GET['token'] ?? '');
if ($token === '') {
    die('Encuesta no disponible');
}

$stmt = $pdo->prepare("SELECT * FROM ecommerce_encuestas WHERE token_publico = ? AND activo = 1");
$stmt->execute([$token]);
$encuesta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$encuesta) {
    die('Encuesta no encontrada');
}

$fecha_base = new DateTime($encuesta['fecha_entrega']);
$fecha_limite = (clone $fecha_base)->modify('+30 days');
$hoy = new DateTime();
$expirada = $hoy > $fecha_limite;

$stmt = $pdo->prepare("SELECT * FROM ecommerce_encuesta_preguntas WHERE encuesta_id = ? ORDER BY orden");
$stmt->execute([$encuesta['id']]);
$preguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$expirada) {
    try {
        $email = trim($_POST['email'] ?? '');
        $respuestas = $_POST['respuestas'] ?? [];

        if (empty($respuestas)) {
            throw new Exception('Completá al menos una respuesta');
        }

        $stmtIns = $pdo->prepare("INSERT INTO ecommerce_encuesta_respuestas (encuesta_id, pregunta_id, respuesta, email) VALUES (?, ?, ?, ?)");
        foreach ($preguntas as $p) {
            $pid = $p['id'];
            $valor = $respuestas[$pid] ?? '';
            if ($valor === '') continue;
            $stmtIns->execute([$encuesta['id'], $pid, $valor, $email ?: null]);
        }

        $mensaje = '¡Gracias por completar la encuesta!';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <h1><?= htmlspecialchars($encuesta['titulo']) ?></h1>
            <p class="text-muted"><?= nl2br(htmlspecialchars($encuesta['descripcion'] ?? '')) ?></p>
            <p class="text-muted">Disponible hasta: <?= $fecha_limite->format('d/m/Y') ?></p>

            <?php if ($expirada): ?>
                <div class="alert alert-danger">La encuesta expiró.</div>
            <?php else: ?>
                <?php if ($mensaje): ?>
                    <div class="alert alert-success">✓ <?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Email (opcional)</label>
                                <input type="email" class="form-control" name="email">
                            </div>

                            <?php foreach ($preguntas as $p): ?>
                                <div class="mb-3">
                                    <label class="form-label"><?= htmlspecialchars($p['pregunta']) ?></label>
                                    <?php if ($p['tipo'] === 'opcion'): ?>
                                        <?php $ops = json_decode($p['opciones_json'] ?? '[]', true) ?: []; ?>
                                        <select class="form-select" name="respuestas[<?= $p['id'] ?>]">
                                            <option value="">Seleccionar...</option>
                                            <?php foreach ($ops as $op): ?>
                                                <option value="<?= htmlspecialchars($op) ?>"><?= htmlspecialchars($op) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($p['tipo'] === 'escala'): ?>
                                        <select class="form-select" name="respuestas[<?= $p['id'] ?>]">
                                            <option value="">Seleccionar...</option>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <option value="<?= $i ?>"><?= $i ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    <?php else: ?>
                                        <textarea class="form-control" name="respuestas[<?= $p['id'] ?>]" rows="2"></textarea>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <button type="submit" class="btn btn-primary w-100">Enviar respuestas</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
