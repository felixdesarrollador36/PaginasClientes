
<?php
$pageTitle = 'Tienda WOC';
$page = 'shop';
$pageCss = 'shop-modern';
$shopCtrl = new ShopController();
$userId = isLoggedIn() ? currentUserId() : 0;

$categories = $shopCtrl->getCategories();
$selectedCategory = $_GET['category'] ?? null;
$search = $_GET['q'] ?? '';

$items = $shopCtrl->getItems($selectedCategory, $search);
$featuredItems = $shopCtrl->getFeaturedItems(1); // Solo 1 destacado para banner
$userCoins = $userId ? $shopCtrl->getUserCoins($userId) : 0;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<link rel="stylesheet" href="../assets/css/pages/shop-modern.css">
<div class="shop-modern-wrapper">
    <aside class="shop-modern-sidebar">
        <h3>Categorías</h3>
        <form method="GET" action="<?= url('shop') ?>">
            <ul>
                <?php foreach ($categories as $cat): ?>
                <li>
                    <label>
                        <input type="radio" name="category" value="<?= $cat['id'] ?>" <?= $selectedCategory == $cat['id'] ? 'checked' : '' ?> onchange="this.form.submit()">
                        <?= $cat['name'] ?>
                    </label>
                </li>
                <?php endforeach; ?>
                <li>
                    <label>
                        <input type="radio" name="category" value="" <?= empty($selectedCategory) ? 'checked' : '' ?> onchange="this.form.submit()">
                        Todos
                    </label>
                </li>
            </ul>
            <h3>Buscar</h3>
            <div class="shop-modern-price-filter">
                <input type="text" name="q" placeholder="🔍 Buscar..." value="<?= htmlspecialchars($search) ?>" style="width:100%">
            </div>
            <button type="submit" class="shop-modern-apply">Filtrar</button>
        </form>
        <div class="shop-modern-social">
            <a href="#">🐦</a>
            <a href="#">📘</a>
            <a href="#">📸</a>
        </div>
    </aside>
    <main class="shop-modern-main">
               <div class="shop-modern-banner">
            <?php if (!empty($featuredItems)): ?>
                <img src="<?= url('assets/shop/' . $featuredItems[0]['image']) ?>" alt="<?= htmlspecialchars($featuredItems[0]['name']) ?>">
            <?php else: ?>
                <img src="<?= url('assets/img/banner.png') ?>" alt="Banner Tienda" style="height:220px;width:100%;object-fit:cover;border-radius:18px;">
            <?php endif; ?>
        </div>
        <div class="shop-modern-products-grid">
            <?php if (empty($items)): ?>
                <div style="color:var(--text-muted);font-size:1.2em;">No hay items en esta categoría.</div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                <div class="shop-modern-product-card">
                    <img src="<?= url('assets/shop/' . $item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    <div class="shop-modern-product-info">
                        <h4><?= htmlspecialchars($item['name']) ?></h4>
                        <span class="shop-modern-platform"><?= $item['platform'] ?? $item['category_name'] ?></span>
                        <div class="shop-modern-price-row">
                            <span class="shop-modern-price">🪙 <?= number_format($item['price_coins']) ?></span>
                            <button class="shop-modern-buy-btn">Comprar</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
