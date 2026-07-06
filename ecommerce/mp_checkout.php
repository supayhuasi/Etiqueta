<?php
require 'config.php';
require 'includes/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pedido_id = intval($_GET['pedido_id'] ?? $_SESSION['pedido_id'] ?? 0);
$public_token = trim($_GET['token'] ?? '');

if ($pedido_id <= 0 && $public_token === '') {
    die("Pedido no especificado");
}

if ($public_token !== '') {
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_pedidos WHERE public_token = ? LIMIT 1");
    $stmt->execute([$public_token]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_pedidos WHERE id = ? LIMIT 1");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$pedido) {
    die("Pedido no encontrado");
}

// Obtener cliente
$stmt = $pdo->prepare("SELECT * FROM ecommerce_clientes WHERE id = ?");
$stmt->execute([$pedido['cliente_id']]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener configuración de Mercado Pago
$stmt = $pdo->query("SELECT * FROM ecommerce_mercadopago_config WHERE activo = 1 LIMIT 1");
$config_mp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config_mp) {
    die("Mercado Pago no está configurado");
}

$public_key = $config_mp['modo'] === 'test'
    ? $config_mp['public_key_test']
    : $config_mp['public_key_produccion'];

if (empty($public_key)) {
    die("Clave pública de Mercado Pago no está configurada para el modo activo.");
}

$pedido_id = (int)$pedido['id'];
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">💳 Pago con Mercado Pago</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <p class="mb-1"><strong>Número de pedido:</strong> <?= htmlspecialchars($pedido['numero_pedido']) ?></p>
                        <p class="mb-1"><strong>Total a pagar:</strong> <span class="text-success">$<?= number_format($pedido['total'], 2, ',', '.') ?></span></p>
                        <p class="text-muted mb-0">Completa los datos de tu tarjeta para procesar el pago directamente con Mercado Pago.</p>
                    </div>

                    <div id="error-container" class="alert alert-danger" style="display:none;"></div>
                    <div id="success-container" class="alert alert-success" style="display:none;"></div>

                    <form id="mp-payment-form" class="needs-validation" novalidate>
                        <input type="hidden" name="pedido_id" value="<?= $pedido_id ?>">
                        <input type="hidden" name="token" id="mp_token" value="">
                        <input type="hidden" name="payment_method_id" id="mp_payment_method_id" value="">

                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <label for="cardNumber" class="form-label">Número de tarjeta</label>
                                <input type="text" id="cardNumber" name="cardNumber" class="form-control" placeholder="1234 5678 9012 3456" autocomplete="cc-number" required data-checkout="cardNumber">
                                <div class="invalid-feedback">Ingresá el número de tarjeta.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="cardExpirationMonth" class="form-label">Mes</label>
                                <input type="text" id="cardExpirationMonth" name="cardExpirationMonth" class="form-control" placeholder="MM" required data-checkout="cardExpirationMonth">
                                <div class="invalid-feedback">Mes inválido.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="cardExpirationYear" class="form-label">Año</label>
                                <input type="text" id="cardExpirationYear" name="cardExpirationYear" class="form-control" placeholder="AA" required data-checkout="cardExpirationYear">
                                <div class="invalid-feedback">Año inválido.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="securityCode" class="form-label">CVV</label>
                                <input type="text" id="securityCode" name="securityCode" class="form-control" placeholder="123" required data-checkout="securityCode">
                                <div class="invalid-feedback">CVV inválido.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="cardholderName" class="form-label">Nombre del titular</label>
                            <input type="text" id="cardholderName" name="cardholderName" class="form-control" placeholder="Juan Pérez" required data-checkout="cardholderName">
                            <div class="invalid-feedback">Ingresá el nombre del titular.</div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="docType" class="form-label">Tipo de documento</label>
                                <select id="docType" name="docType" class="form-select" required data-checkout="docType"></select>
                                <div class="invalid-feedback">Seleccioná un tipo de documento.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="docNumber" class="form-label">Número de documento</label>
                                <input type="text" id="docNumber" name="docNumber" class="form-control" placeholder="12345678" required data-checkout="docNumber">
                                <div class="invalid-feedback">Ingresá el número de documento.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="installments" class="form-label">Cuotas</label>
                            <select id="installments" name="installments" class="form-select">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>" <?= $i === 1 ? 'selected' : '' ?>><?= $i ?> cuota<?= $i > 1 ? 's' : '' ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">Pagar $<?= number_format($pedido['total'], 2, ',', '.') ?></button>
                    </form>

                    <div id="processing-container" class="text-center mt-3" style="display:none;">
                        <div class="spinner-border" role="status"></div>
                        <p class="mt-2">Procesando tu pago...</p>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <a href="carrito.php" class="btn btn-outline-secondary">← Volver al Carrito</a>
            </div>
        </div>
    </div>
</div>

<script src="https://secure.mlstatic.com/sdk/javascript/v1/mercadopago.js"></script>
<script>
const mpPublicKey = <?= json_encode($public_key) ?>;
const mpTotalAmount = <?= json_encode(round((float)$pedido['total'], 2)) ?>;
Mercadopago.setPublishableKey(mpPublicKey);

let mpPaymentMethodId = '';
let mpCardBin = '';

const loadIdentificationTypes = () => {
    Mercadopago.getIdentificationTypes(function(status, response) {
        const select = document.getElementById('docType');
        if (status === 200 && Array.isArray(response)) {
            select.innerHTML = '';
            response.forEach(type => {
                const option = document.createElement('option');
                option.value = type.id;
                option.textContent = type.name;
                select.appendChild(option);
            });
        } else {
            select.innerHTML = '<option value="DNI">DNI</option>';
        }
    });
};

const updateInstallments = (bin) => {
    const installmentsSelect = document.getElementById('installments');
    if (!bin || bin.length < 6) {
        installmentsSelect.innerHTML = '<option value="1">1 cuota</option>';
        mpPaymentMethodId = '';
        return;
    }

    Mercadopago.getInstallments({ bin: bin, amount: mpTotalAmount }, function(status, response) {
        if (status !== 200 || !Array.isArray(response) || response.length === 0) {
            installmentsSelect.innerHTML = '<option value="1">1 cuota</option>';
            mpPaymentMethodId = '';
            return;
        }

        const payerCosts = response[0].payer_costs || [];
        mpPaymentMethodId = response[0].payment_method_id || mpPaymentMethodId;

        if (payerCosts.length === 0) {
            installmentsSelect.innerHTML = '<option value="1">1 cuota</option>';
            return;
        }

        installmentsSelect.innerHTML = '';
        payerCosts.forEach((cost) => {
            const option = document.createElement('option');
            option.value = cost.installments;
            let text = cost.installments + ' cuota' + (cost.installments > 1 ? 's' : '');
            if (cost.recommended_message) {
                text += ' - ' + cost.recommended_message;
            } else if (cost.installments > 1) {
                text += ' de $' + cost.installment_amount.toFixed(2);
            }
            option.textContent = text;
            installmentsSelect.appendChild(option);
        });
    });
};

const normalizeDigits = (value) => value.replace(/\D/g, '');
const getBinFromNumber = (number) => normalizeDigits(number).slice(0, 6);

const showError = (message) => {
    const errorContainer = document.getElementById('error-container');
    errorContainer.style.display = 'block';
    errorContainer.textContent = message;
    document.getElementById('success-container').style.display = 'none';
};

const showSuccess = (message) => {
    const successContainer = document.getElementById('success-container');
    successContainer.style.display = 'block';
    successContainer.textContent = message;
    document.getElementById('error-container').style.display = 'none';
};

const mapStatusToUrl = (status, pedidoId) => {
    switch (status) {
        case 'approved':
            return 'mp_success.php?pedido_id=' + pedidoId;
        case 'pending':
        case 'in_process':
        case 'authorized':
            return 'mp_pending.php?pedido_id=' + pedidoId;
        default:
            return null;
    }
};

const validateCardFields = () => {
    const cardNumber = document.getElementById('cardNumber').value.trim();
    const expMonth = document.getElementById('cardExpirationMonth').value.trim();
    const expYear = document.getElementById('cardExpirationYear').value.trim();
    const securityCode = document.getElementById('securityCode').value.trim();

    if (!Mercadopago.validateCardNumber(cardNumber)) {
        showError('El número de tarjeta no es válido.');
        return false;
    }

    if (!Mercadopago.validateCardExpirationDate(expMonth, expYear)) {
        showError('La fecha de vencimiento no es válida.');
        return false;
    }

    const methodId = mpPaymentMethodId || document.getElementById('mp_payment_method_id').value;
    if (methodId && !Mercadopago.validateSecurityCode(securityCode, methodId)) {
        showError('El CVV no es válido.');
        return false;
    }

    if (securityCode.length < 3 || securityCode.length > 4) {
        showError('El CVV debe tener 3 o 4 dígitos.');
        return false;
    }

    const docNumber = document.getElementById('docNumber').value.trim();
    if (docNumber === '') {
        showError('Ingresá el número de documento.');
        return false;
    }

    return true;
};

const paymentForm = document.getElementById('mp-payment-form');
const cardNumberInput = document.getElementById('cardNumber');
cardNumberInput.addEventListener('input', function() {
    const bin = getBinFromNumber(cardNumberInput.value);
    if (bin.length >= 6 && bin !== mpCardBin) {
        mpCardBin = bin;
        updateInstallments(bin);
    }
});

paymentForm.addEventListener('submit', function(event) {
    event.preventDefault();
    if (!paymentForm.checkValidity()) {
        paymentForm.classList.add('was-validated');
        return;
    }

    if (!validateCardFields()) {
        return;
    }

    document.getElementById('processing-container').style.display = 'block';
    document.getElementById('error-container').style.display = 'none';
    document.getElementById('success-container').style.display = 'none';

    Mercadopago.createToken(paymentForm, function(status, response) {
        if (status === 200 || status === 201) {
            const mpToken = response.id;
            const paymentMethodId = response.card && response.card.payment_method ? response.card.payment_method.id : response.payment_method_id || mpPaymentMethodId || '';
            document.getElementById('mp_token').value = mpToken;
            document.getElementById('mp_payment_method_id').value = paymentMethodId;

            const formData = new FormData(paymentForm);
            fetch('procesar_pago_mp.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('processing-container').style.display = 'none';
                if (data.success) {
                    const redirectUrl = mapStatusToUrl(data.status, <?= $pedido_id ?>);
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                        return;
                    }
                    showSuccess('Pago procesado correctamente. Estado: ' + data.status);
                } else {
                    showError(data.error || 'Error desconocido al procesar el pago.');
                }
            })
            .catch(error => {
                document.getElementById('processing-container').style.display = 'none';
                showError('Error al comunicarse con el servidor: ' + (error.message || error));
            });
        } else {
            document.getElementById('processing-container').style.display = 'none';
            showError(response.cause ? response.cause.map(item => item.description).join(', ') : 'Error al generar el token de la tarjeta. Verificá los datos.');
        }
    });
});

loadIdentificationTypes();
updateInstallments(getBinFromNumber(cardNumberInput.value));
</script>

<?php require 'includes/footer.php'; ?>
