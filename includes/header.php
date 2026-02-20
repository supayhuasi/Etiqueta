<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sistema Tucu Roller</title>
<link href="assets/bootstrap.min.css" rel="stylesheet">
<style>
	.top-promo-bar { background: linear-gradient(90deg,#27ae60 0%,#2ecc71 100%); color: #fff; text-align: center; padding: 10px 16px; position: fixed; top: 0; left: 0; right: 0; z-index: 1050; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,.08); display:flex; align-items:center; justify-content:center; gap:12px; }
	.top-promo-bar .sep { color: rgba(255,255,255,.85); margin: 0 8px; }
	.top-promo-bar .close-btn { position: absolute; right: 12px; top: 8px; background: transparent; border: none; color: #fff; font-size: 18px; cursor: pointer; }
	@media (max-width:600px){ .top-promo-bar { font-size:13px; padding:8px 10px; } .top-promo-bar .hide-mobile { display: none; } }
	body.has-top-promo { padding-top: 48px; }
</style>
</head>
<body>

<div id="topPromoBar" class="top-promo-bar" role="region" aria-label="Promociones principales">
	<span>🚚 <strong>ENVÍO GRATIS</strong></span>
	<span class="sep">|</span>
	<span>💳 <strong>3 CUOTAS SIN INTERÉS</strong></span>
	<span class="sep hide-mobile">|</span>
	<span class="hide-mobile">📏 <strong>INSTALACIÓN GRATIS DENTRO DE TUCUMÁN</strong></span>
	<span class="sep hide-mobile">|</span>
	<span>🔥 <strong>Hasta 35% OFF</strong></span>
	<button id="topPromoClose" class="close-btn" aria-label="Cerrar barra promocional">×</button>
</div>
<script>
(function(){
	try {
		const bar = document.getElementById('topPromoBar');
		const closeBtn = document.getElementById('topPromoClose');
		const storageKey = 'topPromoHidden_v1';
		if (!bar) return;
		if (localStorage.getItem(storageKey) === '1') {
			bar.style.display = 'none';
			document.body.classList.remove('has-top-promo');
		} else {
			document.body.classList.add('has-top-promo');
		}
		if (closeBtn) {
			closeBtn.addEventListener('click', function(){
				bar.style.display = 'none';
				document.body.classList.remove('has-top-promo');
				try { localStorage.setItem(storageKey, '1'); } catch(e){}
			});
		}
	} catch(e) { console.error('topPromo init error', e); }
})();
</script>

<?php require 'navbar.php'; ?>

<div class="container mt-4">
	<!-- WhatsApp float (global) -->
	<a href="https://wa.me/543816165554?text=Hola!%20Quiero%20consultar%20sobre%20cortinas" class="whatsapp-float" target="_blank" rel="noopener noreferrer" aria-label="Contactar por WhatsApp" title="Contactar por WhatsApp" style="position:fixed; bottom:20px; right:20px; z-index:99999;">
		<svg width="35" height="35" fill="white" viewBox="0 0 24 24" aria-hidden="true">
			<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
		</svg>
	</a>
	<style>
		.whatsapp-float { background:#25D366; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 12px rgba(0,0,0,0.3); transition:transform .15s ease; }
		.whatsapp-float:hover{ transform:scale(1.06); }
		@media (prefers-reduced-motion: reduce) { .whatsapp-float { transition: none; } }
	</style>
