<?php
require 'config.php';
require 'includes/header.php';

// Obtener información de la empresa
$stmt = $pdo->query("SELECT * FROM ecommerce_empresa LIMIT 1");
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container py-5">
    <h1>Sobre Nosotros</h1>

    <div class="row mt-5">
        <div class="col-md-6">
            <h2>Nuestra Historia</h2>
            <?php if ($empresa): ?>
                <p><?= nl2br(htmlspecialchars($empresa['about_us'] ?? '')) ?></p>
            <?php else: ?>
                <p>Somos una empresa con más de 20 años de experiencia en la industria de cortinas, toldos y persianas. 
                   Nos dedicamos a ofrecer productos de la más alta calidad con diseños modernos y funcionales.</p>
                <p>Nuestro compromiso es brindar soluciones personalizadas que satisfagan las necesidades de nuestros clientes, 
                   combinando calidad, estilo y durabilidad.</p>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <div class="ratio ratio-16x9">
                <iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" allowfullscreen="" loading="lazy"></iframe>
            </div>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-md-12">
            <h2>¿Por Qué Elegirnos?</h2>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3>🎨</h3>
                    <h5>Diseños Modernos</h5>
                    <p>Amplia variedad de diseños y colores acorde a las tendencias actuales</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3>🏆</h3>
                    <h5>Calidad Garantizada</h5>
                    <p>Productos de la más alta calidad con garantía extendida</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3>🚚</h3>
                    <h5>Envío Rápido</h5>
                    <p>Entrega segura y rápida a toda el país en 48-72 horas</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3>💰</h3>
                    <h5>Precios Justos</h5>
                    <p>Financiación disponible sin interés en cuotas</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-md-12">
            <h2>Contacto</h2>
            <p>¿Tienes dudas? No dudes en contactarnos</p>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h3>📧</h3>
                    <h5>Email</h5>
                    <p><?= $empresa['email'] ?? 'info@tucuroller.com' ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h3>📞</h3>
                    <h5>Teléfono</h5>
                    <p><?= $empresa['telefono'] ?? '(XXXX) XXX-XXXX' ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h3>📍</h3>
                    <h5>Ubicación</h5>
                    <p><?= $empresa['ciudad'] ?? 'Tu ciudad' ?>, <?= $empresa['provincia'] ?? 'Tu provincia' ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
