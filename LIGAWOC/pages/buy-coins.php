<?php
if (!isLoggedIn()) redirect('login');

$pageTitle = 'Comprar Monedas';
$page = 'buy-coins';
$pageCss = 'buy-coins';
$shopCtrl = new ShopController();
$userId = currentUserId();

$packages = $shopCtrl->getCoinPackages();
$userCoins = $shopCtrl->getUserCoins($userId);

require_once __DIR__ . '/../config/paypal.php';
$paypalClientId = trim((string) PAYPAL_CLIENT_ID);
$paypalClientSecret = trim((string) PAYPAL_CLIENT_SECRET);
$paypalReady = $paypalClientId !== ''
    && $paypalClientSecret !== ''
    && stripos($paypalClientId, 'TU_CLIENT_ID') === false
    && stripos($paypalClientSecret, 'TU_CLIENT_SECRET') === false;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<?php if ($paypalReady): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= $paypalClientId ?>&currency=USD&intent=capture&components=buttons"></script>
<?php endif; ?>
<div class="app-wrapper">
<div class="main-content buy-coins-page" data-verify-url="<?= htmlspecialchars(url('buy-coins/verify'), ENT_QUOTES) ?>" data-csrf-token="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>" data-redirect-url="<?= htmlspecialchars(url('inventory'), ENT_QUOTES) ?>">
    <div class="page-header">
        <h1 class="page-title">💰 Comprar WOC Coins</h1>
        <div class="buy-coins-header-actions">
            <span class="coin-balance buy-coins-balance-pill">
                🪙 <?= number_format($userCoins) ?> WOC
            </span>
        </div>
    </div>

    <div class="card mb-4 buy-coins-intro-card">
        <div class="buy-coins-intro-body">
            <h3 class="buy-coins-intro-title">🪙 WOC Coins</h3>
            <p class="buy-coins-intro-text">Usa tus monedas para comprar exclusivos marcos, portadas y más en la tienda.</p>
        </div>
    </div>

    <?php if (!$paypalReady): ?>
    <div class="card mb-4 buy-coins-config-alert" role="alert">
        <h3 class="buy-coins-config-alert-title">PayPal no está configurado</h3>
        <p class="buy-coins-config-alert-text">Completa <strong>PAYPAL_CLIENT_ID</strong> y <strong>PAYPAL_CLIENT_SECRET</strong> en el archivo .env para habilitar los botones de pago.</p>
    </div>
    <?php endif; ?>

    <div class="grid grid-3 buy-coins-grid">
        <?php foreach ($packages as $pkg): ?>
        <div class="card buy-coins-package-card<?= $pkg['bonus_coins'] > 0 ? ' buy-coins-package-card--bonus' : '' ?>">
            <?php if ($pkg['bonus_coins'] > 0): ?>
            <div class="buy-coins-bonus-banner">+<?= $pkg['bonus_coins'] ?> BONUS</div>
            <?php endif; ?>
            <div class="buy-coins-package-body">
                <div class="buy-coins-package-icon">🪙</div>
                <div class="buy-coins-package-amount"><?= number_format($pkg['coins'] + $pkg['bonus_coins']) ?></div>
                <div class="buy-coins-package-label">WOC Coins</div>
                
                <div
                    <?php if ($paypalReady): ?>
                    id="paypal-button-container-<?= $pkg['id'] ?>"
                    class="buy-coins-paypal-container"
                    data-package-id="<?= $pkg['id'] ?>"
                    data-package-coins="<?= (int) $pkg['coins'] ?>"
                    data-package-price="<?= number_format($pkg['price_usd'], 2, '.', '') ?>"
                    ></div>
                    <?php else: ?>
                    class="buy-coins-paypal-disabled"
                    >Configura PayPal para comprar este paquete.</div>
                    <?php endif; ?>
                
                <div class="buy-coins-price">
                    $<?= number_format($pkg['price_usd'], 2) ?> USD
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title">ℹ️ Información</h3>
        </div>
        <div class="buy-coins-info-body">
            <p>• Los pagos se procesan de forma segura a través de PayPal.</p>
            <p>• Las monedas compradas se acreditan inmediatamente a tu cuenta.</p>
            <p>• Las monedas no son reembolsables.</p>
        </div>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
