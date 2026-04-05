<?php
$pageTitle = 'Tienda WOC';
$page = 'shop';
$pageCss = 'shop';
$shopCtrl = new ShopController();
$userId = isLoggedIn() ? currentUserId() : 0;

$categories = $shopCtrl->getCategories();
$selectedCategory = $_GET['category'] ?? null;
$search = $_GET['q'] ?? '';

$items = $shopCtrl->getItems($selectedCategory, $search);
$featuredItems = $shopCtrl->getFeaturedItems(6);
$userCoins = $userId ? $shopCtrl->getUserCoins($userId) : 0;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content shop-page" data-item-api-base="<?= htmlspecialchars(url('api/shop/item/'), ENT_QUOTES) ?>">
    <div class="page-header">
        <h1 class="page-title">🏪 Tienda WOC</h1>
        <?php if ($userId): ?>
        <div class="shop-header-actions">
            <span class="coin-balance shop-balance-pill">
                🪙 <?= number_format($userCoins) ?> WOC
            </span>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($featuredItems) && !$selectedCategory && !$search): ?>
    <div class="mb-4">
        <h3 class="shop-section-title">⭐ Items Destacados</h3>
        <div class="grid grid-6 shop-featured-grid">
            <?php foreach ($featuredItems as $item): ?>
            <div class="shop-item-card shop-item-card--featured" data-open-item-modal="<?= $item['id'] ?>" role="button" tabindex="0">
                <div class="shop-item-image shop-item-image--featured">
                    <img src="<?= url('assets/shop/' . $item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="shop-item-image-media">
                </div>
                <div class="shop-featured-item-name"><?= htmlspecialchars($item['name']) ?></div>
                <div class="shop-featured-item-price">🪙 <?= number_format($item['price_coins']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="shop-category-row">
        <a href="<?= url('shop') ?>" class="btn btn-sm <?= !$selectedCategory ? 'btn-primary' : 'btn-secondary' ?>">Todos</a>
        <?php foreach ($categories as $cat): ?>
        <a href="<?= url('shop?category=' . $cat['id']) ?>" class="btn btn-sm <?= $selectedCategory == $cat['id'] ? 'btn-primary' : 'btn-secondary' ?>">
            <?= $cat['name'] ?>
        </a>
        <?php endforeach; ?>
    </div>

    <form method="GET" action="<?= url('shop') ?>" class="shop-search-form">
        <div class="shop-search-row">
            <input type="text" name="q" class="form-control" placeholder="🔍 Buscar items..." value="<?= htmlspecialchars($search) ?>">
            <?php if ($selectedCategory): ?>
            <input type="hidden" name="category" value="<?= $selectedCategory ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-secondary">Buscar</button>
        </div>
    </form>

    <?php if (empty($items)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">🏪</div>
        <h3 class="empty-state-title">No hay items</h3>
        <p>No se encontraron items en esta categoría.</p>
    </div>
    <?php else: ?>
    <div class="grid grid-4 shop-items-grid">
        <?php foreach ($items as $item): ?>
        <div class="shop-item-card card shop-item-card--catalog" data-open-item-modal="<?= $item['id'] ?>" role="button" tabindex="0">
            <div class="shop-item-image shop-item-image--catalog">
                <img src="<?= url('assets/shop/' . $item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="shop-item-image-media">
            </div>
            <div class="shop-item-body">
                <div class="shop-item-category"><?= $item['category_name'] ?></div>
                <div class="shop-item-name"><?= htmlspecialchars($item['name']) ?></div>
                <div class="shop-item-meta">
                    <span class="shop-item-price">🪙 <?= number_format($item['price_coins']) ?></span>
                    <span class="shop-item-sales"><?= $item['total_sales'] ?> ventas</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- Item Detail Modal -->
<div id="itemModal" class="modal shop-item-modal">
    <div class="card shop-item-modal-card">
        <button type="button" data-close-item-modal class="shop-item-modal-close">&times;</button>
        <div id="itemModalContent">Cargando...</div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
