</main>

<footer class="bg-dark text-white mt-5 py-5">
  <div class="container">
    <div class="row mb-4">
      <div class="col-12">
        <div class="d-flex flex-column flex-md-row gap-2 gap-md-4 align-items-start align-items-md-center">
          <span class="badge bg-success">✅ Fabricación propia</span>
          <span class="badge bg-success">✅ Garantía escrita</span>
          <span class="badge bg-success">✅ Más de 10000 cortinas fabricas e instaladas</span>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-4">
        <h5>🏢 Tucu Roller</h5>
        <p>Somos especialistas en cortinas, toldos y persianas de la más alta calidad.</p>
      </div>
      <div class="col-md-4">
        <h5>Enlaces Útiles</h5>
        <ul class="list-unstyled">
          <li><a href="index.php" class="text-white-50">Inicio</a></li>
          <li><a href="tienda.php" class="text-white-50">Tienda</a></li>
          <li><a href="nosotros.php" class="text-white-50">Nosotros</a></li>
          <li><a href="contacto.php" class="text-white-50">Contacto</a></li>
          <li><a href="politica_privacidad.php" class="text-white-50">Política de privacidad</a></li>
          <li><a href="terminos_servicio.php" class="text-white-50">Términos del servicio</a></li>
        </ul>
      </div>
      <div class="col-md-4">
        <h5>Contacto</h5>
        <p class="text-white-50">
          📧 contacto@tucuroller.com<br>
          📞 (381) 6165554<br>
          📍 Parque Industrial Kanamico - Lules - Tucuman
        </p>
        <?php
          $facebook_url = $redes_menu['facebook'] ?? '';
          $instagram_url = $redes_menu['instagram'] ?? '';
        ?>
        <?php if (!empty($facebook_url) || !empty($instagram_url)): ?>
          <div class="d-flex gap-3 mt-2">
            <?php if (!empty($facebook_url)): ?>
              <a href="<?= htmlspecialchars($facebook_url) ?>" target="_blank" rel="noopener" class="text-white" aria-label="Facebook">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.95v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258V8.05h2.218l-.354 2.325H9.25V16c3.824-.604 6.75-3.934 6.75-7.951z"/>
                </svg>
              </a>
            <?php endif; ?>
            <?php if (!empty($instagram_url)): ?>
              <a href="<?= htmlspecialchars($instagram_url) ?>" target="_blank" rel="noopener" class="text-white" aria-label="Instagram">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M8 0C5.35 0 5.017.01 3.957.058 2.897.107 2.165.3 1.55.536a3.9 3.9 0 0 0-1.4.94A3.9 3.9 0 0 0 .536 2.55C.3 3.165.107 3.897.058 4.957.01 6.017 0 6.35 0 8s.01 1.983.058 3.043c.049 1.06.242 1.792.478 2.407.247.62.57 1.145.94 1.4.255.37.78.693 1.4.94.615.236 1.347.429 2.407.478C6.017 15.99 6.35 16 8 16s1.983-.01 3.043-.058c1.06-.049 1.792-.242 2.407-.478.62-.247 1.145-.57 1.4-.94.37-.255.693-.78.94-1.4.236-.615.429-1.347.478-2.407C15.99 9.983 16 9.65 16 8s-.01-1.983-.058-3.043c-.049-1.06-.242-1.792-.478-2.407a3.9 3.9 0 0 0-.94-1.4 3.9 3.9 0 0 0-1.4-.94c-.615-.236-1.347-.429-2.407-.478C9.983.01 9.65 0 8 0zm0 1.44c1.61 0 1.8.006 2.437.035.589.027.909.125 1.12.208.28.109.48.24.69.45.21.21.341.41.45.69.083.211.181.531.208 1.12.029.637.035.827.035 2.437s-.006 1.8-.035 2.437c-.027.589-.125.909-.208 1.12a2.46 2.46 0 0 1-.45.69 2.46 2.46 0 0 1-.69.45c-.211.083-.531.181-1.12.208-.637.029-.827.035-2.437.035s-1.8-.006-2.437-.035c-.589-.027-.909-.125-1.12-.208a2.46 2.46 0 0 1-.69-.45 2.46 2.46 0 0 1-.45-.69c-.083-.211-.181-.531-.208-1.12C1.446 9.8 1.44 9.61 1.44 8s.006-1.8.035-2.437c.027-.589.125-.909.208-1.12.109-.28.24-.48.45-.69.21-.21.41-.341.69-.45.211-.083.531-.181 1.12-.208C6.2 1.446 6.39 1.44 8 1.44z"/>
                  <path d="M8 3.9a4.1 4.1 0 1 0 0 8.2 4.1 4.1 0 0 0 0-8.2zm0 1.44a2.66 2.66 0 1 1 0 5.32 2.66 2.66 0 0 1 0-5.32zm4.29-1.83a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92z"/>
                </svg>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <hr class="bg-white-50">
    <p class="text-center text-white-50 mb-0">&copy; 2026 Tucu Group. Todos los derechos reservados.</p>
  </div>
</footer>

<?php
$wa_num_clean = preg_replace('/\D+/', '', $whatsapp_num ?? '');
$wa_msg_final = trim($whatsapp_msg ?? '') !== '' ? $whatsapp_msg : 'Hola, quiero hacer una consulta';
$wa_url = $wa_num_clean ? 'https://wa.me/' . $wa_num_clean . '?text=' . urlencode($wa_msg_final) : '';
?>

<?php if (!empty($wa_url)): ?>
  <a href="<?= htmlspecialchars($wa_url) ?>" class="whatsapp-float" target="_blank" rel="noopener" aria-label="Contactar por WhatsApp" title="Contactar por WhatsApp">
    <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
      <path d="M16 0c-8.837 0-16 7.163-16 16 0 2.825 0.737 5.607 2.137 8.048l-2.137 7.952 7.933-2.127c2.42 1.37 5.173 2.127 8.067 2.127 8.837 0 16-7.163 16-16s-7.163-16-16-16zM16 29.467c-2.482 0-4.908-0.646-7.07-1.87l-0.507-0.292-5.293 1.421 1.435-5.342-0.304-0.527c-1.308-2.266-2.261-4.768-2.261-7.856 0-7.384 6.083-13.467 13.467-13.467s13.467 6.083 13.467 13.467-6.083 13.467-13.467 13.467zM21.62 18.337c-0.394-0.197-2.332-1.149-2.694-1.28s-0.625-0.197-0.888 0.197c-0.263 0.394-1.02 1.28-1.25 1.543s-0.46 0.296-0.854 0.099c-0.394-0.197-1.663-0.612-3.166-1.953-1.171-1.043-1.961-2.331-2.191-2.725s-0.024-0.607 0.173-0.804c0.177-0.177 0.394-0.461 0.591-0.691s0.263-0.394 0.394-0.657c0.131-0.263 0.066-0.493-0.033-0.691s-0.888-2.141-1.217-2.932c-0.32-0.77-0.645-0.666-0.888-0.678-0.23-0.012-0.493-0.015-0.756-0.015s-0.691 0.099-1.053 0.493c-0.362 0.394-1.381 1.348-1.381 3.289s1.414 3.816 1.611 4.079c0.197 0.263 2.788 4.257 6.753 5.968 0.944 0.407 1.681 0.65 2.255 0.833 0.952 0.302 1.818 0.259 2.502 0.157 0.763-0.114 2.332-0.953 2.661-1.874s0.329-1.711 0.23-1.874c-0.099-0.164-0.362-0.263-0.756-0.461z"/>
    </svg>
  </a>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php
$carrito_activo = !empty($_SESSION['carrito']) && count($_SESSION['carrito']) > 0 && !empty($_SESSION['cliente_id']);
?>
<?php if ($carrito_activo): ?>
<script>
  (function () {
    var sent = false;
    function sendAbandonedCart() {
      if (sent) return;
      sent = true;
      if (navigator.sendBeacon) {
        navigator.sendBeacon('carrito_abandonado.php', '1');
      } else {
        fetch('carrito_abandonado.php', {
          method: 'POST',
          keepalive: true,
          headers: { 'Content-Type': 'text/plain' },
          body: '1'
        });
      }
    }
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden') {
        sendAbandonedCart();
      }
    });
    window.addEventListener('pagehide', sendAbandonedCart);
  })();
</script>
<?php endif; ?>
</body>
</html>

<?php
if (ob_get_level()) {
  ob_end_flush();
}
?>
