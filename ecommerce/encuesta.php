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
$email_valor = trim((string)($_POST['email'] ?? ''));
$respuestas_previas = isset($_POST['respuestas']) && is_array($_POST['respuestas']) ? $_POST['respuestas'] : [];

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

<style>
    .encuesta-page {
        background:
            radial-gradient(circle at top left, rgba(13, 110, 253, .12), transparent 32%),
            radial-gradient(circle at top right, rgba(111, 66, 193, .10), transparent 28%),
            linear-gradient(180deg, #f4f7ff 0%, #ffffff 52%, #f8fbff 100%);
    }
    .encuesta-shell {
        position: relative;
    }
    .encuesta-hero,
    .encuesta-form-card {
        border: 1px solid rgba(255,255,255,.55);
        border-radius: 24px;
        box-shadow: 0 22px 55px rgba(15, 23, 42, 0.12);
        overflow: hidden;
    }
    .encuesta-hero {
        position: relative;
        background: linear-gradient(135deg, #0b3ea9 0%, #0d6efd 52%, #6f42c1 100%);
        color: #fff;
    }
    .encuesta-hero::after {
        content: '';
        position: absolute;
        inset: auto -40px -70px auto;
        width: 180px;
        height: 180px;
        background: rgba(255,255,255,.12);
        border-radius: 50%;
        filter: blur(2px);
    }
    .encuesta-hero .lead,
    .encuesta-hero .small {
        color: rgba(255,255,255,.92) !important;
    }
    .encuesta-topline {
        letter-spacing: .08em;
        opacity: .92;
    }
    .encuesta-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .65rem;
        margin-top: 1rem;
    }
    .encuesta-badge {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        background: rgba(255,255,255,.14);
        border: 1px solid rgba(255,255,255,.24);
        border-radius: 999px;
        padding: .48rem .85rem;
        font-size: .9rem;
        backdrop-filter: blur(4px);
    }
    .encuesta-form-card {
        background: rgba(255,255,255,.92);
        backdrop-filter: blur(8px);
    }
    .encuesta-form-card .card-body {
        padding: 1.5rem;
    }
    .encuesta-intro-box {
        border: 1px solid #e5edff;
        background: linear-gradient(180deg, #fdfefe 0%, #f7faff 100%);
        border-radius: 18px;
        padding: 1rem 1.1rem;
    }
    .encuesta-question {
        position: relative;
        border: 1px solid #e9eef8;
        border-left: 5px solid #0d6efd;
        border-radius: 18px;
        background: linear-gradient(180deg, #fcfdff 0%, #f8fbff 100%);
        padding: 1rem 1rem .9rem;
        margin-bottom: 1rem;
        transition: all .2s ease;
    }
    .encuesta-question:hover {
        border-color: #cfe0ff;
        box-shadow: 0 12px 28px rgba(13, 110, 253, 0.10);
        transform: translateY(-1px);
    }
    .encuesta-question-label {
        display: block;
        font-weight: 800;
        font-size: 1.06rem;
        color: #14213d;
        margin-bottom: .65rem;
        line-height: 1.4;
    }
    .encuesta-question-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        margin-right: .55rem;
        border-radius: 50%;
        background: linear-gradient(135deg, #e8f0ff 0%, #d4e3ff 100%);
        color: #0d4fcf;
        font-weight: 800;
        font-size: .92rem;
        box-shadow: inset 0 0 0 1px rgba(13,110,253,.08);
    }
    .encuesta-scale {
        display: flex;
        gap: .55rem;
        flex-wrap: wrap;
    }
    .encuesta-scale .form-check {
        margin: 0;
    }
    .encuesta-scale .form-check-input {
        display: none;
    }
    .encuesta-scale .form-check-label {
        min-width: 50px;
        text-align: center;
        border-radius: 14px;
        border: 1px solid #d6e4ff;
        background: #fff;
        color: #0d3b8e;
        font-weight: 800;
        padding: .65rem .85rem;
        cursor: pointer;
        transition: all .2s ease;
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.04);
    }
    .encuesta-scale .form-check-label:hover {
        border-color: #8db4ff;
        transform: translateY(-1px);
    }
    .encuesta-scale .form-check-input:checked + .form-check-label {
        background: linear-gradient(135deg, #0d6efd 0%, #3c8bff 100%);
        color: #fff;
        border-color: #0d6efd;
        box-shadow: 0 10px 22px rgba(13, 110, 253, .26);
    }
    .encuesta-scale-legend {
        display: flex;
        justify-content: space-between;
        gap: .75rem;
        font-size: .85rem;
        color: #6c757d;
        margin-top: .45rem;
    }
    .encuesta-help {
        font-size: .88rem;
        color: #6c757d;
    }
    .encuesta-submit {
        border: 0;
        border-radius: 14px;
        background: linear-gradient(135deg, #0b5ed7 0%, #0d6efd 55%, #4f8cff 100%);
        box-shadow: 0 14px 28px rgba(13, 110, 253, .22);
        font-weight: 700;
        letter-spacing: .01em;
        padding-top: .85rem;
        padding-bottom: .85rem;
    }
    .encuesta-submit:hover {
        filter: brightness(1.02);
        transform: translateY(-1px);
    }
    @media (max-width: 767.98px) {
        .encuesta-form-card .card-body {
            padding: 1rem;
        }
        .encuesta-question {
            padding: .9rem;
        }
        .encuesta-meta,
        .encuesta-scale-legend {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="container py-5 encuesta-page">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7 encuesta-shell">
            <div class="card encuesta-hero mb-4">
                <div class="card-body p-4 p-md-5 position-relative">
                    <div class="small text-uppercase fw-semibold mb-2 encuesta-topline">Encuesta de satisfacción</div>
                    <h1 class="fw-bold mb-3"><?= htmlspecialchars($encuesta['titulo']) ?></h1>
                    <?php if (!empty($encuesta['descripcion'])): ?>
                        <p class="lead mb-3"><?= nl2br(htmlspecialchars($encuesta['descripcion'])) ?></p>
                    <?php endif; ?>
                    <div class="encuesta-meta">
                        <div class="encuesta-badge">📅 Disponible hasta: <?= $fecha_limite->format('d/m/Y') ?></div>
                        <div class="encuesta-badge">📝 <?= count($preguntas) ?> pregunta(s)</div>
                        <div class="encuesta-badge">✨ Respuesta rápida y simple</div>
                    </div>
                </div>
            </div>

            <?php if ($expirada): ?>
                <div class="alert alert-danger shadow-sm">La encuesta expiró.</div>
            <?php else: ?>
                <?php if ($mensaje): ?>
                    <div class="alert alert-success shadow-sm">✓ <?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger shadow-sm"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="card encuesta-form-card">
                    <div class="card-body">
                        <div class="encuesta-intro-box mb-4">
                            <h4 class="fw-bold mb-1">Tu opinión nos ayuda a mejorar</h4>
                            <p class="text-muted mb-0">Respondé en unos segundos. Dejamos la experiencia más clara, elegante y fácil de completar.</p>
                        </div>

                        <form method="POST">
                            <div class="mb-4">
                                <label class="form-label fw-bold">Email (opcional)</label>
                                <input type="email" class="form-control form-control-lg" name="email" value="<?= htmlspecialchars($email_valor) ?>" placeholder="tuemail@ejemplo.com">
                                <div class="encuesta-help mt-1">Si querés, podés dejarlo para contactarte por tu respuesta.</div>
                            </div>

                            <?php foreach ($preguntas as $index => $p): ?>
                                <?php $valorActual = (string)($respuestas_previas[$p['id']] ?? ''); ?>
                                <div class="encuesta-question">
                                    <label class="encuesta-question-label">
                                        <span class="encuesta-question-number"><?= $index + 1 ?></span>
                                        <?= htmlspecialchars($p['pregunta']) ?>
                                    </label>

                                    <?php if ($p['tipo'] === 'opcion'): ?>
                                        <?php $ops = json_decode($p['opciones_json'] ?? '[]', true) ?: []; ?>
                                        <select class="form-select form-select-lg" name="respuestas[<?= $p['id'] ?>]">
                                            <option value="">Seleccionar...</option>
                                            <?php foreach ($ops as $op): ?>
                                                <option value="<?= htmlspecialchars($op) ?>" <?= $valorActual === (string)$op ? 'selected' : '' ?>><?= htmlspecialchars($op) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($p['tipo'] === 'escala'): ?>
                                        <div class="encuesta-scale mb-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" id="pregunta_<?= $p['id'] ?>_<?= $i ?>" name="respuestas[<?= $p['id'] ?>]" value="<?= $i ?>" <?= $valorActual === (string)$i ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="pregunta_<?= $p['id'] ?>_<?= $i ?>"><?= $i ?></label>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="encuesta-scale-legend">
                                            <span>Muy baja</span>
                                            <span>Excelente</span>
                                        </div>
                                    <?php else: ?>
                                        <textarea class="form-control" name="respuestas[<?= $p['id'] ?>]" rows="3" placeholder="Escribí tu respuesta..."><?= htmlspecialchars($valorActual) ?></textarea>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <button type="submit" class="btn btn-primary btn-lg w-100 encuesta-submit">Enviar respuestas ✨</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
