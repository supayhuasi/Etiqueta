<?php
require 'includes/header.php';

// Asegurar columnas SEO
try {
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_empresa LIKE 'seo_title'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_empresa ADD COLUMN seo_title VARCHAR(255) NULL AFTER descripcion");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_empresa LIKE 'seo_description'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_empresa ADD COLUMN seo_description VARCHAR(255) NULL AFTER seo_title");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_empresa LIKE 'seo_image'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_empresa ADD COLUMN seo_image VARCHAR(255) NULL AFTER seo_description");
    }
    $col = $pdo->query("SHOW COLUMNS FROM ecommerce_empresa LIKE 'favicon'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_empresa ADD COLUMN favicon VARCHAR(255) NULL AFTER logo");
    }
} catch (Exception $e) {
    // Ignorar errores de migración
}

// Obtener o crear registro de empresa
$stmt = $pdo->query("SELECT * FROM ecommerce_empresa LIMIT 1");
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empresa) {
    $pdo->exec("INSERT INTO ecommerce_empresa (nombre, email) VALUES ('Mi Empresa', 'info@empresa.com')");
    $stmt = $pdo->query("SELECT * FROM ecommerce_empresa LIMIT 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $email = $_POST['email'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $direccion = $_POST['direccion'] ?? '';
    $ciudad = $_POST['ciudad'] ?? '';
    $provincia = $_POST['provincia'] ?? '';
    $pais = $_POST['pais'] ?? '';
    $horario_atencion = $_POST['horario_atencion'] ?? '';
    $about_us = $_POST['about_us'] ?? '';
    $seo_title = $_POST['seo_title'] ?? '';
    $seo_description = $_POST['seo_description'] ?? '';
    $seo_image = $_POST['seo_image'] ?? '';
    $terminos = $_POST['terminos'] ?? '';
    $privacidad = $_POST['privacidad'] ?? '';
    $facebook = $_POST['facebook'] ?? '';
    $instagram = $_POST['instagram'] ?? '';
    $whatsapp = $_POST['whatsapp'] ?? '';
    $whatsapp_mensaje = $_POST['whatsapp_mensaje'] ?? '';
    $cuit = $_POST['cuit'] ?? '';
    $responsabilidad_fiscal = $_POST['responsabilidad_fiscal'] ?? '';
    $iibb = $_POST['iibb'] ?? '';
    $regimen_iva = $_POST['regimen_iva'] ?? '';
    
    if (empty($nombre) || empty($email)) {
        $error = "Nombre y email son obligatorios";
    } else {
        try {
            // Procesar logo y favicon
            $logo = $empresa['logo'] ?? null;
            $favicon = $empresa['favicon'] ?? null;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
                $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif'];
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                
                if (!in_array($ext, $tipos_permitidos)) {
                    $error = "Tipo de imagen no permitido";
                } else if ($_FILES['logo']['size'] > 5242880) {
                    $error = "La imagen es muy grande (máx 5MB)";
                } else {
                    $logo = "logo_" . time() . "." . $ext;
                    $dir_logo = "../../uploads/";
                    if (!is_dir($dir_logo)) {
                        mkdir($dir_logo, 0755, true);
                    }
                    if (!move_uploaded_file($_FILES['logo']['tmp_name'], $dir_logo . $logo)) {
                        $error = "Error al subir la imagen";
                        $logo = $empresa['logo'] ?? null;
                    }
                }
            }

            if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] == 0) {
                $tipos_permitidos = ['png', 'jpg', 'jpeg', 'ico', 'svg'];
                $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $tipos_permitidos)) {
                    $error = "Tipo de favicon no permitido";
                } else if ($_FILES['favicon']['size'] > 2097152) {
                    $error = "El favicon es muy grande (máx 2MB)";
                } else {
                    $favicon = "favicon_" . time() . "." . $ext;
                    $dir_logo = "../../uploads/";
                    if (!is_dir($dir_logo)) {
                        mkdir($dir_logo, 0755, true);
                    }
                    if (!move_uploaded_file($_FILES['favicon']['tmp_name'], $dir_logo . $favicon)) {
                        $error = "Error al subir el favicon";
                        $favicon = $empresa['favicon'] ?? null;
                    }
                }
            }
            
            if (!isset($error)) {
                $redes_sociales = json_encode([
                    'facebook' => $facebook,
                    'instagram' => $instagram,
                    'whatsapp' => $whatsapp,
                    'whatsapp_mensaje' => $whatsapp_mensaje
                ]);
                
                $stmt = $pdo->prepare("
                    UPDATE ecommerce_empresa 
                    SET nombre = ?, descripcion = ?, seo_title = ?, seo_description = ?, seo_image = ?, logo = ?, favicon = ?, email = ?, telefono = ?,
                        direccion = ?, ciudad = ?, provincia = ?, pais = ?,
                        horario_atencion = ?, about_us = ?, terminos_condiciones = ?,
                        politica_privacidad = ?, redes_sociales = ?, cuit = ?,
                        responsabilidad_fiscal = ?, iibb = ?, regimen_iva = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $nombre, $descripcion, $seo_title, $seo_description, $seo_image, $logo, $favicon, $email, $telefono,
                    $direccion, $ciudad, $provincia, $pais,
                    $horario_atencion, $about_us, $terminos, $privacidad,
                    $redes_sociales, $cuit, $responsabilidad_fiscal, $iibb, $regimen_iva,
                    $empresa['id']
                ]);
                
                $mensaje = "Información actualizada correctamente";
                // Recargar datos
                $stmt_reload = $pdo->prepare("SELECT * FROM ecommerce_empresa WHERE id = ?");
                $stmt_reload->execute([$empresa['id']]);
                $empresa = $stmt_reload->fetch(PDO::FETCH_ASSOC) ?? $empresa;
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

$redes = json_decode($empresa['redes_sociales'] ?? '{}', true) ?? [];
?>

<h1>Información de la Empresa</h1>

<?php if (isset($mensaje)): ?>
    <div class="alert alert-success"><?= $mensaje ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header">
                    <h5>Datos Básicos</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">Nombre de la Empresa *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($empresa['nombre'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($empresa['email'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción Corta</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="2"><?= htmlspecialchars($empresa['descripcion'] ?? '') ?></textarea>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">SEO (buscadores y redes)</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="seo_title" class="form-label">Título SEO</label>
                                <input type="text" class="form-control" id="seo_title" name="seo_title" value="<?= htmlspecialchars($empresa['seo_title'] ?? '') ?>" placeholder="Ej: Tucu Roller | Cortinas y Toldos">
                                <small class="text-muted">Si está vacío, se usa el nombre y la página.</small>
                            </div>
                            <div class="mb-3">
                                <label for="seo_description" class="form-label">Descripción SEO</label>
                                <textarea class="form-control" id="seo_description" name="seo_description" rows="2" maxlength="255"><?= htmlspecialchars($empresa['seo_description'] ?? '') ?></textarea>
                                <small class="text-muted">Recomendado 140-160 caracteres.</small>
                            </div>
                            <div class="mb-3">
                                <label for="seo_image" class="form-label">Imagen SEO (URL)</label>
                                <input type="url" class="form-control" id="seo_image" name="seo_image" value="<?= htmlspecialchars($empresa['seo_image'] ?? '') ?>" placeholder="https://.../imagen.jpg">
                                <small class="text-muted">Si está vacío, se usa el logo.</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="about_us" class="form-label">Acerca de Nosotros</label>
                        <textarea class="form-control" id="about_us" name="about_us" rows="4"><?= htmlspecialchars($empresa['about_us'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5>Datos Fiscales</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cuit" class="form-label">CUIT</label>
                            <input type="text" class="form-control" id="cuit" name="cuit" placeholder="Ej: 20-12345678-9" value="<?= htmlspecialchars($empresa['cuit'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="responsabilidad_fiscal" class="form-label">Responsabilidad Fiscal</label>
                            <select class="form-select" id="responsabilidad_fiscal" name="responsabilidad_fiscal">
                                <option value="">Selecciona...</option>
                                <option value="Responsable Inscripto" <?= ($empresa['responsabilidad_fiscal'] ?? '') === 'Responsable Inscripto' ? 'selected' : '' ?>>Responsable Inscripto</option>
                                <option value="Monotributista" <?= ($empresa['responsabilidad_fiscal'] ?? '') === 'Monotributista' ? 'selected' : '' ?>>Monotributista</option>
                                <option value="Exento" <?= ($empresa['responsabilidad_fiscal'] ?? '') === 'Exento' ? 'selected' : '' ?>>Exento</option>
                                <option value="No Responsable" <?= ($empresa['responsabilidad_fiscal'] ?? '') === 'No Responsable' ? 'selected' : '' ?>>No Responsable</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="iibb" class="form-label">Ingresos Brutos</label>
                            <input type="text" class="form-control" id="iibb" name="iibb" placeholder="Ej: 123456789" value="<?= htmlspecialchars($empresa['iibb'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="regimen_iva" class="form-label">Régimen IVA</label>
                            <select class="form-select" id="regimen_iva" name="regimen_iva">
                                <option value="">Selecciona...</option>
                                <option value="IVA 0%" <?= ($empresa['regimen_iva'] ?? '') === 'IVA 0%' ? 'selected' : '' ?>>IVA 0%</option>
                                <option value="IVA 10.5%" <?= ($empresa['regimen_iva'] ?? '') === 'IVA 10.5%' ? 'selected' : '' ?>>IVA 10.5%</option>
                                <option value="IVA 21%" <?= ($empresa['regimen_iva'] ?? '') === 'IVA 21%' ? 'selected' : '' ?>>IVA 21%</option>
                                <option value="IVA 27%" <?= ($empresa['regimen_iva'] ?? '') === 'IVA 27%' ? 'selected' : '' ?>>IVA 27%</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5>Contacto y Ubicación</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($empresa['telefono'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="horario_atencion" class="form-label">Horario de Atención</label>
                            <input type="text" class="form-control" id="horario_atencion" name="horario_atencion" placeholder="Ej: Lunes a Viernes 9:00-18:00" value="<?= htmlspecialchars($empresa['horario_atencion'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección</label>
                        <input type="text" class="form-control" id="direccion" name="direccion" value="<?= htmlspecialchars($empresa['direccion'] ?? '') ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="ciudad" class="form-label">Ciudad</label>
                            <input type="text" class="form-control" id="ciudad" name="ciudad" value="<?= htmlspecialchars($empresa['ciudad'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="provincia" class="form-label">Provincia</label>
                            <input type="text" class="form-control" id="provincia" name="provincia" value="<?= htmlspecialchars($empresa['provincia'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="pais" class="form-label">País</label>
                            <input type="text" class="form-control" id="pais" name="pais" value="<?= htmlspecialchars($empresa['pais'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5>Redes Sociales</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="facebook" class="form-label">Facebook</label>
                        <input type="url" class="form-control" id="facebook" name="facebook" placeholder="https://facebook.com/..." value="<?= htmlspecialchars($redes['facebook'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="instagram" class="form-label">Instagram</label>
                        <input type="url" class="form-control" id="instagram" name="instagram" placeholder="https://instagram.com/..." value="<?= htmlspecialchars($redes['instagram'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="whatsapp" class="form-label">WhatsApp</label>
                        <input type="tel" class="form-control" id="whatsapp" name="whatsapp" placeholder="Ej: +54 9 3624 123456" value="<?= htmlspecialchars($redes['whatsapp'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="whatsapp_mensaje" class="form-label">Mensaje inicial WhatsApp</label>
                        <input type="text" class="form-control" id="whatsapp_mensaje" name="whatsapp_mensaje" placeholder="Ej: Hola, quiero consultar por..." value="<?= htmlspecialchars($redes['whatsapp_mensaje'] ?? '') ?>">
                        <small class="text-muted">Se usa como mensaje pre-cargado al abrir WhatsApp.</small>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5>Términos y Política</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="terminos" class="form-label">Términos y Condiciones</label>
                        <textarea class="form-control" id="terminos" name="terminos" rows="4"><?= htmlspecialchars($empresa['terminos_condiciones'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="privacidad" class="form-label">Política de Privacidad</label>
                        <textarea class="form-control" id="privacidad" name="privacidad" rows="4"><?= htmlspecialchars($empresa['politica_privacidad'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header">
                    <h5>Logo</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($empresa['logo'])): ?>
                        <div class="mb-3">
                            <img src="../uploads/<?= htmlspecialchars($empresa['logo']) ?>" class="img-fluid rounded" style="max-height: 200px;">
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center mb-3">
                            Sin logo
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                        <small class="text-muted">PNG, JPG o GIF (máx 5MB)</small>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5>Favicon</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($empresa['favicon'])): ?>
                        <div class="mb-3">
                            <img src="../uploads/<?= htmlspecialchars($empresa['favicon']) ?>" class="img-fluid rounded" style="max-height: 64px;">
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center mb-3">
                            Sin favicon
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <input type="file" class="form-control" id="favicon" name="favicon" accept="image/png,image/jpeg,image/x-icon,image/svg+xml">
                        <small class="text-muted">PNG, JPG, ICO o SVG (máx 2MB)</small>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex gap-2">
                        <a href="index.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require 'includes/footer.php'; ?>
