<?php
require 'includes/header.php';

$producto_id = $_GET['producto_id'] ?? 0;
if ($producto_id <= 0) die("Producto no especificado");

$stmt = $pdo->prepare("SELECT * FROM ecommerce_productos WHERE id = ? AND tipo_precio = 'variable'");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) die("Producto variable no encontrado");

// Obtener matriz de precios existente
$stmt = $pdo->prepare("
    SELECT * FROM ecommerce_matriz_precios 
    WHERE producto_id = ? 
    ORDER BY alto_cm, ancho_cm
");
$stmt->execute([$producto_id]);
$matriz = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar generación automática
if ($_POST['accion'] === 'generar' && isset($_POST['precio_base_matriz'])) {
    try {
        $precio_base = floatval($_POST['precio_base_matriz']);
        
        // Eliminar matriz existente
        $pdo->prepare("DELETE FROM ecommerce_matriz_precios WHERE producto_id = ?")->execute([$producto_id]);
        
        // Generar nueva matriz
        for ($alto = 10; $alto <= 600; $alto += 10) {
            for ($ancho = 10; $ancho <= 600; $ancho += 10) {
                $area_cm2 = ($alto * $ancho) / 100; // convertir a dm²
                $precio = $precio_base * ($area_cm2 / 100); // precio base por dm²
                
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_matriz_precios (producto_id, alto_cm, ancho_cm, precio, stock)
                    VALUES (?, ?, ?, ?, 0)
                ");
                $stmt->execute([$producto_id, $alto, $ancho, round($precio, 2)]);
            }
        }
        
        // Recargar matriz
        $stmt = $pdo->prepare("
            SELECT * FROM ecommerce_matriz_precios 
            WHERE producto_id = ? 
            ORDER BY alto_cm, ancho_cm
        ");
        $stmt->execute([$producto_id]);
        $matriz = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $mensaje = "Matriz generada automáticamente (" . count($matriz) . " registros)";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Procesar importación CSV
if ($_POST['accion'] === 'importar' && isset($_FILES['archivo_csv'])) {
    try {
        if ($_FILES['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error al subir el archivo");
        }

        $tmpPath = $_FILES['archivo_csv']['tmp_name'];
        $handle = fopen($tmpPath, 'r');
        if (!$handle) {
            throw new Exception("No se pudo abrir el archivo");
        }

        // Detectar delimitador
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            throw new Exception("Archivo vacío");
        }
        $delim = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delim);
        if (!$header) {
            fclose($handle);
            throw new Exception("No se pudo leer el encabezado");
        }

        $normalized = array_map(function($h) {
            return strtolower(trim($h));
        }, $header);

        $hasHeader = in_array('alto_cm', $normalized, true) || in_array('alto', $normalized, true);
        $index = [
            'alto_cm' => array_search('alto_cm', $normalized, true),
            'ancho_cm' => array_search('ancho_cm', $normalized, true),
            'precio' => array_search('precio', $normalized, true),
            'stock' => array_search('stock', $normalized, true),
        ];

        // Detectar formato matriz: primera celda vacía y encabezados numéricos (anchos)
        $headerValues = array_map(function($v) {
            return trim((string)$v);
        }, $header);
        $firstCell = $headerValues[0] ?? '';
        $numericHeaders = 0;
        for ($i = 1; $i < count($headerValues); $i++) {
            if ($headerValues[$i] !== '' && is_numeric(str_replace(',', '.', $headerValues[$i]))) {
                $numericHeaders++;
            }
        }
        $isMatrixFormat = ($firstCell === '' || !is_numeric(str_replace(',', '.', $firstCell))) && $numericHeaders >= 2;

        if (!$hasHeader && !$isMatrixFormat) {
            // Usar formato fijo: alto_cm, ancho_cm, precio, stock
            $index = [
                'alto_cm' => 0,
                'ancho_cm' => 1,
                'precio' => 2,
                'stock' => 3,
            ];
        }

        $reemplazar = isset($_POST['reemplazar']) && $_POST['reemplazar'] === '1';
        $pdo->beginTransaction();

        if ($reemplazar) {
            $pdo->prepare("DELETE FROM ecommerce_matriz_precios WHERE producto_id = ?")->execute([$producto_id]);
        }

        $stmtCheck = $pdo->prepare("SELECT id FROM ecommerce_matriz_precios WHERE producto_id = ? AND alto_cm = ? AND ancho_cm = ?");
        $stmtUpd = $pdo->prepare("UPDATE ecommerce_matriz_precios SET precio = ?, stock = ? WHERE id = ?");
        $stmtIns = $pdo->prepare("INSERT INTO ecommerce_matriz_precios (producto_id, alto_cm, ancho_cm, precio, stock) VALUES (?, ?, ?, ?, ?)");

        $total = 0;
        $importados = 0;
        $omitidos = 0;

        if ($isMatrixFormat) {
            $anchos = [];
            for ($i = 1; $i < count($headerValues); $i++) {
                $anchos[$i] = intval(str_replace(',', '.', $headerValues[$i]));
            }

            while (($row = fgetcsv($handle, 0, $delim)) !== false) {
                $alto_cm = isset($row[0]) ? intval(str_replace(',', '.', $row[0])) : 0;
                if ($alto_cm < 10 || $alto_cm > 600) {
                    continue;
                }

                for ($i = 1; $i < count($row); $i++) {
                    $total++;
                    $ancho_cm = $anchos[$i] ?? 0;
                    $precio = isset($row[$i]) ? floatval(str_replace(',', '.', $row[$i])) : 0;
                    $stock = 0;

                    if ($ancho_cm < 10 || $ancho_cm > 600 || $precio <= 0) {
                        $omitidos++;
                        continue;
                    }

                    $stmtCheck->execute([$producto_id, $alto_cm, $ancho_cm]);
                    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                    if ($existing) {
                        $stmtUpd->execute([$precio, $stock, $existing['id']]);
                    } else {
                        $stmtIns->execute([$producto_id, $alto_cm, $ancho_cm, $precio, $stock]);
                    }
                    $importados++;
                }
            }
        } else {
            if (!$hasHeader) {
                // Procesar la primera fila como datos
                $dataRows = [$header];
            } else {
                $dataRows = [];
            }

            while (($row = fgetcsv($handle, 0, $delim)) !== false) {
                $dataRows[] = $row;
            }

            foreach ($dataRows as $row) {
                $total++;
                $alto_cm = isset($row[$index['alto_cm']]) ? intval($row[$index['alto_cm']]) : 0;
                $ancho_cm = isset($row[$index['ancho_cm']]) ? intval($row[$index['ancho_cm']]) : 0;
                $precio = isset($row[$index['precio']]) ? floatval(str_replace(',', '.', $row[$index['precio']])) : 0;
                $stock = isset($row[$index['stock']]) ? intval($row[$index['stock']]) : 0;

                if ($alto_cm < 10 || $alto_cm > 600 || $ancho_cm < 10 || $ancho_cm > 600 || $precio <= 0) {
                    $omitidos++;
                    continue;
                }

                $stmtCheck->execute([$producto_id, $alto_cm, $ancho_cm]);
                $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $stmtUpd->execute([$precio, $stock, $existing['id']]);
                } else {
                    $stmtIns->execute([$producto_id, $alto_cm, $ancho_cm, $precio, $stock]);
                }
                $importados++;
            }
        }

        fclose($handle);
        $pdo->commit();

        // Recargar matriz
        $stmt = $pdo->prepare("
            SELECT * FROM ecommerce_matriz_precios 
            WHERE producto_id = ? 
            ORDER BY alto_cm, ancho_cm
        ");
        $stmt->execute([$producto_id]);
        $matriz = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mensaje = "Importación completada: {$importados} filas importadas, {$omitidos} omitidas";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error: " . $e->getMessage();
    }
}

// Procesar agregar/actualizar entrada
if ($_POST['accion'] === 'guardar' && isset($_POST['alto_cm'])) {
    try {
        $id = intval($_POST['id'] ?? 0);
        $alto_cm = intval($_POST['alto_cm']);
        $ancho_cm = intval($_POST['ancho_cm']);
        $precio = floatval($_POST['precio']);
        $stock = intval($_POST['stock'] ?? 0);
        
        if ($alto_cm < 10 || $alto_cm > 600 || $ancho_cm < 10 || $ancho_cm > 600 || $precio <= 0) {
            $error = "Dimensiones o precio inválido";
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE ecommerce_matriz_precios 
                    SET alto_cm = ?, ancho_cm = ?, precio = ?, stock = ?
                    WHERE id = ? AND producto_id = ?
                ");
                $stmt->execute([$alto_cm, $ancho_cm, $precio, $stock, $id, $producto_id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_matriz_precios (producto_id, alto_cm, ancho_cm, precio, stock)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$producto_id, $alto_cm, $ancho_cm, $precio, $stock]);
            }
            
            // Recargar matriz
            $stmt = $pdo->prepare("
                SELECT * FROM ecommerce_matriz_precios 
                WHERE producto_id = ? 
                ORDER BY alto_cm, ancho_cm
            ");
            $stmt->execute([$producto_id]);
            $matriz = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $mensaje = "Entrada guardada correctamente";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Procesar eliminación
if ($_POST['accion'] === 'eliminar' && isset($_POST['id'])) {
    try {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM ecommerce_matriz_precios WHERE id = ? AND producto_id = ?")->execute([$id, $producto_id]);
        
        // Recargar matriz
        $stmt = $pdo->prepare("
            SELECT * FROM ecommerce_matriz_precios 
            WHERE producto_id = ? 
            ORDER BY alto_cm, ancho_cm
        ");
        $stmt->execute([$producto_id]);
        $matriz = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $mensaje = "Entrada eliminada";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<h1>Matriz de Precios - <?= htmlspecialchars($producto['nombre']) ?></h1>

<?php if (isset($mensaje)): ?>
    <div class="alert alert-success"><?= $mensaje ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header">
                <h5>Generar Automáticamente</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="generar">
                    <div class="mb-3">
                        <label for="precio_base_matriz" class="form-label">Precio Base por dm² ($)</label>
                        <input type="number" class="form-control" id="precio_base_matriz" name="precio_base_matriz" step="0.01" value="<?= $producto['precio_base'] ?>" required>
                        <small class="text-muted">Se generarán 870 registros (87 altos × 10 anchos)</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Esto elimará la matriz existente y generará una nueva')">Generar Matriz</button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5>Importar desde CSV</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="importar">
                    <div class="mb-3">
                        <label for="archivo_csv" class="form-label">Archivo CSV</label>
                        <input type="file" class="form-control" id="archivo_csv" name="archivo_csv" accept=".csv,.txt" required>
                        <small class="text-muted d-block">Formato: alto_cm, ancho_cm, precio, stock (stock opcional). Se acepta encabezado.</small>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="reemplazar" name="reemplazar">
                        <label class="form-check-label" for="reemplazar">Reemplazar matriz existente</label>
                    </div>
                    <button type="submit" class="btn btn-warning w-100">Importar CSV</button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5>Copiar desde Otro Producto</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Copia la matriz de precios de otro producto y aplica un ajuste porcentual opcional.</p>
                <a href="copiar_matriz.php?producto_id=<?= $producto_id ?>" class="btn btn-info w-100">
                    <i class="bi bi-files"></i> Copiar Matriz
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5>Agregar/Editar Entrada</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="guardar">
                    <div class="mb-3">
                        <label for="alto_cm" class="form-label">Alto (cm)</label>
                        <select class="form-select" id="alto_cm" name="alto_cm" required>
                            <option value="">Seleccionar...</option>
                            <?php for ($i = 10; $i <= 600; $i += 10): ?>
                                <option value="<?= $i ?>"><?= $i ?> cm</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="ancho_cm" class="form-label">Ancho (cm)</label>
                        <select class="form-select" id="ancho_cm" name="ancho_cm" required>
                            <option value="">Seleccionar...</option>
                            <?php for ($i = 10; $i <= 600; $i += 10): ?>
                                <option value="<?= $i ?>"><?= $i ?> cm</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="precio" class="form-label">Precio ($)</label>
                        <input type="number" class="form-control" id="precio" name="precio" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="stock" class="form-label">Stock</label>
                        <input type="number" class="form-control" id="stock" name="stock" value="0">
                    </div>
                    <button type="submit" class="btn btn-success w-100">Guardar Entrada</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5>Matriz de Precios Actual (<?= count($matriz) ?> registros)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($matriz)): ?>
                    <p class="text-muted">No hay registros en la matriz. Genera automáticamente o agrega manualmente.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Alto (cm)</th>
                                    <th>Ancho (cm)</th>
                                    <th>Área (m²)</th>
                                    <th>Precio ($)</th>
                                    <th>Stock</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($matriz as $item): ?>
                                    <tr>
                                        <td><?= $item['alto_cm'] ?></td>
                                        <td><?= $item['ancho_cm'] ?></td>
                                        <td><?= number_format(($item['alto_cm'] * $item['ancho_cm']) / 10000, 2) ?></td>
                                        <td>$<?= number_format($item['precio'], 2) ?></td>
                                        <td><?= $item['stock'] ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar?')">×</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="productos.php" class="btn btn-secondary">Volver a Productos</a>
</div>

<?php require 'includes/footer.php'; ?>
