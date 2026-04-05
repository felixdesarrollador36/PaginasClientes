<?php
if (!isLoggedIn()) redirect('login');

require_once __DIR__ . '/../controllers/ShopController.php';

$pageTitle = 'Mi Inventario';
$page = 'inventory';
$pageCss = 'inventory';
$shopCtrl = new ShopController();
$userId = currentUserId();

$inventory = $shopCtrl->getUserInventory($userId);
$userCoins = $shopCtrl->getUserCoins($userId);

// Debug: show equipped items
$equipped = $shopCtrl->getEquippedItems($userId);
$debugMsg = 'Equipped items: ' . count($equipped) . ' - IDs: ' . implode(', ', array_map(function($e) { return $e['item_id']; }, $equipped));

$itemsByCategory = [];
foreach ($inventory as $item) {
    $type = $item['type'];
    if (!isset($itemsByCategory[$type])) {
        $itemsByCategory[$type] = [];
    }
    $itemsByCategory[$type][] = $item;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div
    class="main-content inventory-page"
    data-csrf-token="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>"
    data-equip-base="<?= htmlspecialchars(url('inventory/equip/'), ENT_QUOTES) ?>"
    data-unequip-base="<?= htmlspecialchars(url('inventory/unequip/'), ENT_QUOTES) ?>"
>
    <div class="page-header">
        <h1 class="page-title">🎒 Mi Inventario</h1>
        <div class="inventory-header-actions">
            <span class="coin-balance inventory-coin-balance">
                🪙 <?= number_format($userCoins) ?> WOC
            </span>
            <a href="<?= url('shop') ?>" class="btn btn-secondary">🛒 Ir a la Tienda</a>
        </div>
    </div>

    <?php if (empty($inventory)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">🎒</div>
        <h3 class="empty-state-title">Inventario vacío</h3>
        <p>¡Visita la tienda para comprar items!</p>
        <a href="<?= url('shop') ?>" class="btn btn-primary inventory-empty-link">🏪 Ver Tienda</a>
    </div>
    <?php else: ?>
    
    <div class="grid grid-3 inventory-grid">
        <?php foreach ($itemsByCategory as $type => $items): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title inventory-card-title">
                    <?php 
                    $icons = ['marco' => '🖼️', 'portada' => '🏞️', 'avatar' => '👤', 'badge' => '🏅'];
                    echo ($icons[$type] ?? '📦') . ' ' . ucfirst($type . 's');
                    ?>
                </h3>
            </div>
            <div class="inventory-items-grid">
                <?php foreach ($items as $item): ?>
                <?php $canToggle = ($type === 'marco' || $type === 'portada'); ?>
                <div
                    class="inventory-item<?= $canToggle ? ' inventory-item--toggleable' : '' ?>"
                    <?php if ($canToggle): ?>
                    data-toggle-equip
                    data-item-id="<?= $item['id'] ?>"
                    data-is-equipped="<?= (int) $item['is_equipped'] ?>"
                    role="button"
                    tabindex="0"
                    <?php endif; ?>
                >
                    <div class="inventory-item-media<?= $item['is_equipped'] ? ' inventory-item-media--equipped' : '' ?>">
                        <img src="<?= url('assets/shop/' . $item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="inventory-item-image">
                    </div>
                    <?php if ($item['is_equipped']): ?>
                    <div class="inventory-item-equipped-badge">✓</div>
                    <?php endif; ?>
                    <div class="inventory-item-name"><?= htmlspecialchars($item['name']) ?></div>
                    <?php if ($type === 'marco' || $type === 'portada'): ?>
                    <button
                        type="button"
                        data-toggle-equip
                        data-item-id="<?= $item['id'] ?>"
                        data-is-equipped="<?= (int) $item['is_equipped'] ?>"
                        class="btn btn-sm inventory-item-toggle-btn <?= $item['is_equipped'] ? 'btn-danger' : 'btn-primary' ?>"
                    >
                        <?= $item['is_equipped'] ? '❌ Quitar' : '✅ Equipar' ?>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
