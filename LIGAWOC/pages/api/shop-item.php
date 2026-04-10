<?php
/**
 * Shop API - Get item details
 */
$page = 'api';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/ShopController.php';

header('Content-Type: text/html; charset=utf-8');

if (!isset($segments)) {
    $route = isset($_GET['route']) ? trim($_GET['route'], '/') : '';
    $segments = $route ? explode('/', $route) : [];
}


$action = $_GET['action'] ?? ($segments[2] ?? '');
$id = intval($_GET['item_id'] ?? ($segments[3] ?? 0));

$shopCtrl = new ShopController();
$userId = isLoggedIn() ? currentUserId() : 0;

if ($action === 'purchase' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $userId = isLoggedIn() ? currentUserId() : 0;
    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'Debes iniciar sesión']);
        exit;
    }
    $itemId = intval($_POST['item_id'] ?? 0);
    if (!$itemId) {
        echo json_encode(['success' => false, 'error' => 'ID de producto inválido']);
        exit;
    }
    $shopCtrl = new ShopController();
    $result = $shopCtrl->purchaseItem($userId, $itemId);
    echo json_encode($result);
    exit;
}

if ($action === 'item' && $id > 0) {
    $item = $shopCtrl->getItem($id);
    if (!$item) {
        echo '<p>Item no encontrado</p>';
        exit;
    }

    $owned = $userId ? $shopCtrl->db->fetch("SELECT id FROM user_inventory WHERE user_id = ? AND item_id = ?", [$userId, $id]) : null;
    $userCoins = $userId ? $shopCtrl->getUserCoins($userId) : 0;
    $canAfford = $userCoins >= $item['price_coins'];
    ?>
    <div class="shop-item-modal-media">
        <img src="<?= '../../assets/shop/' . $item['image'] ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="shop-item-modal-image">
    </div>
    <div class="shop-item-modal-body">
        <span class="shop-item-modal-category"><?= $item['category_name'] ?></span>
        <h2 class="shop-item-modal-title"><?= htmlspecialchars($item['name']) ?></h2>
        <p class="shop-item-modal-description"><?= htmlspecialchars($item['description'] ?? 'Sin descripción') ?></p>
        
        <div class="shop-item-modal-summary">
            <div>
                <div class="shop-item-modal-label">Diseñador</div>
                <div class="shop-item-modal-value"><?= htmlspecialchars($item['designer_name']) ?></div>
            </div>
            <div class="shop-item-modal-price-block">
                <div class="shop-item-modal-label">Precio</div>
                <div class="shop-item-modal-price">🪙 <?= number_format($item['price_coins']) ?></div>
            </div>
        </div>

        <?php if (!$userId): ?>
            <a href="<?= url('login') ?>" class="btn btn-primary shop-item-modal-cta">🔑 Inicia sesión para comprar</a>
        <?php elseif ($owned): ?>
            <div class="shop-item-modal-actions">
                <button class="btn btn-success shop-item-modal-action" disabled>✅ Ya lo tienes</button>
                <?php if ($item['type'] === 'marco' || $item['type'] === 'portada'): ?>
                <a href="<?= url('inventory/equip/' . $item['id']) ?>" class="btn btn-primary shop-item-modal-action shop-item-modal-cta">👤 Equipar</a>
                <?php endif; ?>
            </div>
        <?php elseif ($canAfford): ?>
            <form method="POST" action="<?= url('shop/purchase') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                <button type="submit" class="btn btn-primary shop-item-modal-cta">🛒 Comprar por <?= number_format($item['price_coins']) ?> WOC</button>
            </form>
        <?php else: ?>
            <a href="<?= url('buy-coins') ?>" class="btn btn-warning shop-item-modal-cta">⚠️ No tienes suficientes monedas</a>
        <?php endif; ?>
        
        <div class="shop-item-modal-sales">
            <?= $item['total_sales'] ?> ventas
        </div>
    </div>
    <?php
    exit;
}

echo '<p>API de tienda</p>';
