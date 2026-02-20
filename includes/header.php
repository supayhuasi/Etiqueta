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
