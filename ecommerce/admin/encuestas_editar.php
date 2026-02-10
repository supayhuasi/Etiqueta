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

$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM ecommerce_encuestas WHERE id = ?");
$stmt->execute([$id]);
$encuesta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$encuesta) {
    die('Encuesta no encontrada');
}

$stmt = $pdo->prepare("SELECT * FROM ecommerce_encuesta_preguntas WHERE encuesta_id = ? ORDER BY orden");
$stmt->execute([$id]);
$preguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $fecha_entrega = $_POST['fecha_entrega'] ?? '';
        $activo = isset($_POST['activo']) ? 1 : 0;
        $preguntas_post = $_POST['preguntas'] ?? [];

        if ($titulo === '' || $fecha_entrega === '') {
            throw new Exception('Título y fecha de entrega son obligatorios');
        }

        $stmt = $pdo->prepare("UPDATE ecommerce_encuestas SET titulo = ?, descripcion = ?, fecha_entrega = ?, activo = ? WHERE id = ?");
        $stmt->execute([$titulo, $descripcion, $fecha_entrega, $activo, $id]);

        $pdo->prepare("DELETE FROM ecommerce_encuesta_preguntas WHERE encuesta_id = ?")->execute([$id]);

        $stmtQ = $pdo->prepare("INSERT INTO ecommerce_encuesta_preguntas (encuesta_id, pregunta, tipo, opciones_json, orden) VALUES (?, ?, ?, ?, ?)");
        $orden = 0;
        foreach ($preguntas_post as $p) {
            $texto = trim($p['texto'] ?? '');
            $tipo = $p['tipo'] ?? 'texto';
            $opciones = trim($p['opciones'] ?? '');
            if ($texto === '') continue;
            $opciones_json = null;
            if ($tipo === 'opcion' && $opciones !== '') {
                $opciones_json = json_encode(array_values(array_filter(array_map('trim', explode(',', $opciones)))));
            }
            $stmtQ->execute([$id, $texto, $tipo, $opciones_json, $orden]);
            $orden++;
        }

        $mensaje = 'Encuesta actualizada';
        $stmt = $pdo->prepare("SELECT * FROM ecommerce_encuesta_preguntas WHERE encuesta_id = ? ORDER BY orden");
        $stmt->execute([$id]);
        $preguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $encuesta['titulo'] = $titulo;
        $encuesta['descripcion'] = $descripcion;
        $encuesta['fecha_entrega'] = $fecha_entrega;
        $encuesta['activo'] = $activo;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$link_publico = '../encuesta.php?token=' . urlencode($encuesta['token_publico']);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>✏️ Editar Encuesta</h1>
        <p class="text-muted">Link público: <a href="<?= htmlspecialchars($link_publico) ?>" target="_blank">Abrir</a></p>
    </div>
    <a href="encuestas.php" class="btn btn-secondary">← Volver</a>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success">✓ <?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" id="formEncuesta">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Título *</label>
                    <input type="text" class="form-control" name="titulo" value="<?= htmlspecialchars($encuesta['titulo']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Fecha de entrega *</label>
                    <input type="date" class="form-control" name="fecha_entrega" value="<?= htmlspecialchars($encuesta['fecha_entrega']) ?>" required>
                </div>
            </div>
            <div class="mt-3">
                <label class="form-label">Descripción</label>
                <textarea class="form-control" name="descripcion" rows="3"><?= htmlspecialchars($encuesta['descripcion'] ?? '') ?></textarea>
            </div>
            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= $encuesta['activo'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="activo">Activa</label>
            </div>

            <hr>
            <h5>Preguntas</h5>
            <div id="preguntasContainer"></div>
            <button type="button" class="btn btn-outline-primary mt-2" onclick="agregarPregunta()">+ Agregar pregunta</button>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
let preguntaIndex = 0;
const preguntasExistentes = <?= json_encode($preguntas) ?>;

function agregarPregunta(data = null) {
    preguntaIndex++;
    const item = data || { pregunta: '', tipo: 'texto', opciones_json: null };
    const opciones = item.opciones_json ? (JSON.parse(item.opciones_json) || []).join(', ') : '';

    const html = `
        <div class="card mb-3" id="pregunta_${preguntaIndex}">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Pregunta</label>
                        <input type="text" class="form-control" name="preguntas[${preguntaIndex}][texto]" value="${item.pregunta || ''}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select class="form-select" name="preguntas[${preguntaIndex}][tipo]" onchange="toggleOpciones(${preguntaIndex})">
                            <option value="texto" ${item.tipo === 'texto' ? 'selected' : ''}>Texto</option>
                            <option value="opcion" ${item.tipo === 'opcion' ? 'selected' : ''}>Opciones</option>
                            <option value="escala" ${item.tipo === 'escala' ? 'selected' : ''}>Escala 1-5</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="opciones_${preguntaIndex}" style="display:${item.tipo === 'opcion' ? 'block' : 'none'};">
                        <label class="form-label">Opciones</label>
                        <input type="text" class="form-control" name="preguntas[${preguntaIndex}][opciones]" value="${opciones}" placeholder="Ej: Rojo, Azul, Verde">
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-danger mt-2" onclick="eliminarPregunta(${preguntaIndex})">Eliminar</button>
            </div>
        </div>
    `;

    document.getElementById('preguntasContainer').insertAdjacentHTML('beforeend', html);
}

function toggleOpciones(index) {
    const select = document.querySelector(`#pregunta_${index} select`);
    const cont = document.getElementById(`opciones_${index}`);
    if (!select || !cont) return;
    cont.style.display = select.value === 'opcion' ? 'block' : 'none';
}

function eliminarPregunta(index) {
    const el = document.getElementById(`pregunta_${index}`);
    if (el) el.remove();
}

if (preguntasExistentes.length) {
    preguntasExistentes.forEach(p => agregarPregunta(p));
} else {
    agregarPregunta();
}
</script>

<?php require 'includes/footer.php'; ?>
