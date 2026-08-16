        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    if ('serviceWorker' in navigator && window.ADMIN_URL) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(window.ADMIN_URL + 'sw.js');
        });
    }
</script>
</body>
</html>
<?php
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
