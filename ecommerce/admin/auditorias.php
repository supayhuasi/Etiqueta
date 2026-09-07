<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/audit_helper.php';

$tabla = trim((string)($_GET['tabla'] ?? ''));
$registro = isset($_GET['registro_id']) && is_numeric($_GET['registro_id']) ? (int)$_GET['registro_id'] : null;

auditoria_asegurar_tabla($pdo);

$params = [];
$where = [];
if ($tabla !== '') { $where[] = 'tabla = ?'; $params[] = $tabla; }
if ($registro !== null) { $where[] = 'registro_id = ?'; $params[] = $registro; }

$sql = 'SELECT * FROM ecommerce_auditorias' . (!empty($where) ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC LIMIT 500';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$audits = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Auditorías</h1>
        <a href="index.php" class="btn btn-secondary">← Volver</a>
    </div>

    <form method="get" class="row g-2 mb-3">
        <div class="col-auto">
            <input type="text" name="tabla" class="form-control" placeholder="Tabla (ej: ecommerce_cotizaciones)" value="<?= htmlspecialchars($tabla) ?>">
        </div>
        <div class="col-auto">
            <input type="number" name="registro_id" class="form-control" placeholder="ID registro" value="<?= htmlspecialchars($registro ?? '') ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-primary">Filtrar</button>
        </div>
    </form>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tabla</th>
                            <th>Registro</th>
                            <th>Acción</th>
                            <th>Usuario</th>
                            <th>Datos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audits as $a):
                            $userName = null;
                            if (!empty($a['usuario_id'])) {
                                $stmtU = $pdo->prepare("SELECT COALESCE(NULLIF(TRIM(nombre), ''), usuario) AS nombre FROM usuarios WHERE id = ? LIMIT 1");
                                $stmtU->execute([(int)$a['usuario_id']]);
                                $rowU = $stmtU->fetch(PDO::FETCH_ASSOC);
                                $userName = $rowU ? $rowU['nombre'] : null;
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($a['created_at']) ?></td>
                            <td><?= htmlspecialchars($a['tabla']) ?></td>
                            <td><?= htmlspecialchars($a['registro_id']) ?></td>
                            <td><?= htmlspecialchars($a['accion']) ?></td>
                            <td><?= htmlspecialchars($userName ?? '') ?></td>
                            <td><pre style="margin:0;white-space:pre-wrap;"><?= htmlspecialchars($a['datos_json'] ?? '') ?></pre></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
