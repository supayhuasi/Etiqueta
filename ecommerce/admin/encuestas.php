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

$encuesta_ver_id = isset($_GET['ver']) ? (int)$_GET['ver'] : 0;
$encuesta_detalle = null;
$resumen_preguntas = [];
$respuestas_detalle = [];
$total_respuestas_registros = 0;
$total_envios_estimados = 0;

$stmt = $pdo->query("
    SELECT e.*,
           (SELECT COUNT(*) FROM ecommerce_encuesta_respuestas r WHERE r.encuesta_id = e.id) AS respuestas,
           (
               SELECT COUNT(DISTINCT CONCAT(COALESCE(NULLIF(TRIM(r.email), ''), 'anonimo'), '|', DATE_FORMAT(r.fecha_creacion, '%Y-%m-%d %H:%i:%s')))
               FROM ecommerce_encuesta_respuestas r
               WHERE r.encuesta_id = e.id
           ) AS envios_estimados
    FROM ecommerce_encuestas e
    ORDER BY e.fecha_creacion DESC
");
$encuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($encuesta_ver_id > 0) {
    $stmtDetalle = $pdo->prepare("SELECT * FROM ecommerce_encuestas WHERE id = ? LIMIT 1");
    $stmtDetalle->execute([$encuesta_ver_id]);
    $encuesta_detalle = $stmtDetalle->fetch(PDO::FETCH_ASSOC);

    if ($encuesta_detalle) {
        $stmtTotales = $pdo->prepare("
            SELECT COUNT(*) AS respuestas,
                   COUNT(DISTINCT CONCAT(COALESCE(NULLIF(TRIM(email), ''), 'anonimo'), '|', DATE_FORMAT(fecha_creacion, '%Y-%m-%d %H:%i:%s'))) AS envios_estimados
            FROM ecommerce_encuesta_respuestas
            WHERE encuesta_id = ?
        ");
        $stmtTotales->execute([$encuesta_ver_id]);
        $totales = $stmtTotales->fetch(PDO::FETCH_ASSOC) ?: [];
        $total_respuestas_registros = (int)($totales['respuestas'] ?? 0);
        $total_envios_estimados = (int)($totales['envios_estimados'] ?? 0);

        $stmtResumen = $pdo->prepare("
            SELECT p.id, p.pregunta, p.tipo, p.orden,
                   COUNT(r.id) AS cantidad_respuestas,
                   AVG(CASE WHEN p.tipo = 'escala' AND r.respuesta REGEXP '^[0-9]+(\\.[0-9]+)?$' THEN CAST(r.respuesta AS DECIMAL(10,2)) END) AS promedio_escala,
                   MAX(r.fecha_creacion) AS ultima_respuesta
            FROM ecommerce_encuesta_preguntas p
            LEFT JOIN ecommerce_encuesta_respuestas r ON r.pregunta_id = p.id AND r.encuesta_id = p.encuesta_id
            WHERE p.encuesta_id = ?
            GROUP BY p.id, p.pregunta, p.tipo, p.orden
            ORDER BY p.orden ASC, p.id ASC
        ");
        $stmtResumen->execute([$encuesta_ver_id]);
        $resumen_preguntas = $stmtResumen->fetchAll(PDO::FETCH_ASSOC);

        $stmtRespuestas = $pdo->prepare("
            SELECT r.email, r.respuesta, r.fecha_creacion, p.pregunta, p.tipo
            FROM ecommerce_encuesta_respuestas r
            INNER JOIN ecommerce_encuesta_preguntas p ON p.id = r.pregunta_id
            WHERE r.encuesta_id = ?
            ORDER BY r.fecha_creacion DESC, p.orden ASC, r.id DESC
            LIMIT 300
        ");
        $stmtRespuestas->execute([$encuesta_ver_id]);
        $respuestas_detalle = $stmtRespuestas->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>📝 Encuestas</h1>
        <p class="text-muted">Crear y compartir encuestas</p>
    </div>
    <a href="encuestas_crear.php" class="btn btn-primary">+ Nueva Encuesta</a>
</div>

<style>
    .respuesta-larga {
        white-space: pre-wrap;
        word-break: break-word;
        max-width: 520px;
    }
</style>

<div class="card mb-4">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
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
                            <td>
                                <div><strong><?= (int)$encuesta['respuestas'] ?></strong> registro(s)</div>
                                <small class="text-muted"><?= (int)($encuesta['envios_estimados'] ?? 0) ?> envío(s) aprox.</small>
                            </td>
                            <td>
                                <span class="badge bg-<?= $encuesta['activo'] ? 'success' : 'secondary' ?>">
                                    <?= $encuesta['activo'] ? 'Activa' : 'Inactiva' ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= htmlspecialchars($link_publico) ?>" target="_blank">Abrir</a>
                            </td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="encuestas.php?ver=<?= $encuesta['id'] ?>" class="btn btn-sm <?= $encuesta_ver_id === (int)$encuesta['id'] ? 'btn-info text-white' : 'btn-outline-info' ?>">👁 Ver respuestas</a>
                                    <a href="encuestas_editar.php?id=<?= $encuesta['id'] ?>" class="btn btn-sm btn-primary">✏️ Editar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($encuesta_detalle): ?>
    <div class="card mb-4">
        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <strong>Respuestas de:</strong> <?= htmlspecialchars($encuesta_detalle['titulo']) ?>
            </div>
            <a href="encuestas.php" class="btn btn-sm btn-light">Cerrar vista</a>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100 bg-light">
                        <div class="text-muted small">Registros guardados</div>
                        <div class="h4 mb-0"><?= number_format($total_respuestas_registros, 0, ',', '.') ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100 bg-light">
                        <div class="text-muted small">Envíos estimados</div>
                        <div class="h4 mb-0"><?= number_format($total_envios_estimados, 0, ',', '.') ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100 bg-light">
                        <div class="text-muted small">Fecha de entrega</div>
                        <div class="h4 mb-0"><?= htmlspecialchars($encuesta_detalle['fecha_entrega']) ?></div>
                    </div>
                </div>
            </div>

            <h5 class="mb-3">Resumen por pregunta</h5>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Pregunta</th>
                            <th>Tipo</th>
                            <th>Respuestas</th>
                            <th>Promedio</th>
                            <th>Última respuesta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($resumen_preguntas)): ?>
                            <tr><td colspan="6" class="text-center text-muted">Sin preguntas cargadas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($resumen_preguntas as $i => $fila): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($fila['pregunta']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($fila['tipo']) ?></span></td>
                                    <td><?= number_format((int)($fila['cantidad_respuestas'] ?? 0), 0, ',', '.') ?></td>
                                    <td>
                                        <?php if (($fila['tipo'] ?? '') === 'escala' && $fila['promedio_escala'] !== null): ?>
                                            <strong><?= number_format((float)$fila['promedio_escala'], 2, ',', '.') ?>/5</strong>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($fila['ultima_respuesta']) ? htmlspecialchars($fila['ultima_respuesta']) : '<span class="text-muted">Sin respuestas</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h5 class="mb-3">Últimas respuestas</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Email</th>
                            <th>Pregunta</th>
                            <th>Respuesta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($respuestas_detalle)): ?>
                            <tr><td colspan="4" class="text-center text-muted">Todavía no hay respuestas para esta encuesta.</td></tr>
                        <?php else: ?>
                            <?php foreach ($respuestas_detalle as $respuesta): ?>
                                <tr>
                                    <td><?= htmlspecialchars($respuesta['fecha_creacion']) ?></td>
                                    <td><?= htmlspecialchars($respuesta['email'] ?: 'Anónimo') ?></td>
                                    <td><?= htmlspecialchars($respuesta['pregunta']) ?></td>
                                    <td class="respuesta-larga"><?= nl2br(htmlspecialchars((string)$respuesta['respuesta'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
