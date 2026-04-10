
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
        <!-- Botón hamburguesa solo en móvil, integrado al header -->
        <div class="shop-mobile-menu-btn-wrap">
            <button class="shop-mobile-menu-btn" id="openSidebarBtn" aria-label="Abrir menú" style="display:none;">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="16" cy="16" r="16" fill="#181c24"/>
                    <rect x="9" y="13" width="14" height="2.2" rx="1.1" fill="#fff"/>
                    <rect x="9" y="17" width="14" height="2.2" rx="1.1" fill="#fff"/>
                </svg>
            </button>
        </div>
    <aside class="shop-modern-sidebar" id="shopSidebar">
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

        <div class="shop-modern-products-grid">
            <?php if (empty($items)): ?>
                <div style="color:var(--text-muted);font-size:1.2em;">No hay items en esta categoría.</div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                <div class="shop-modern-product-card" 
                    data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>"
                    data-image="<?= url('assets/shop/' . $item['image']) ?>"
                    data-category="<?= htmlspecialchars($item['category_name'] ?? $item['platform'], ENT_QUOTES) ?>"
                    data-designer="<?= htmlspecialchars($item['designer'] ?? 'Desconocido', ENT_QUOTES) ?>"
                    data-price="<?= number_format($item['price_coins']) ?>"
                    data-id="<?= $item['id'] ?>"
                    onclick="openProductModal(this)">
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

        <!-- Modal Producto -->
        <div id="product-modal" class="shop-modal-overlay" style="display:none;">
            <div class="shop-modal-content">
                <button class="shop-modal-close" onclick="closeProductModal()">&times;</button>
                <div class="shop-modal-img-wrap">
                    <img id="modal-product-img" src="" alt="" />
                </div>
                <div class="shop-modal-info">
                    <div class="shop-modal-category" id="modal-product-category"></div>
                    <h2 class="shop-modal-title" id="modal-product-title"></h2>
                    <div class="shop-modal-designer" id="modal-product-designer"></div>
                    <div class="shop-modal-price-row">
                        <span class="shop-modal-price" id="modal-product-price"></span>
                        <button class="shop-modal-buy-btn" id="modal-buy-btn">Comprar</button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
// Mostrar/ocultar menú lateral en móvil
document.addEventListener('DOMContentLoaded', function() {
    function isMobile() {
        return window.innerWidth <= 800;
    }
    const sidebar = document.getElementById('shopSidebar');
    const openBtn = document.getElementById('openSidebarBtn');
    if (sidebar && openBtn) {
        function updateSidebarDisplay() {
            if (isMobile()) {
                sidebar.classList.add('drawer');
                openBtn.style.display = 'block';
                sidebar.style.transform = 'translateX(-105%)';
            } else {
                sidebar.classList.remove('drawer');
                openBtn.style.display = 'none';
                sidebar.style.transform = '';
            }
        }
        updateSidebarDisplay();
        window.addEventListener('resize', updateSidebarDisplay);
        openBtn.addEventListener('click', function() {
            sidebar.style.transform = 'translateX(0)';
            sidebar.classList.add('active');
        });
        // Cerrar al hacer click fuera o deslizar
        sidebar.addEventListener('click', function(e) {
            if (e.target === sidebar && sidebar.classList.contains('drawer')) {
                sidebar.style.transform = 'translateX(-105%)';
                sidebar.classList.remove('active');
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                sidebar.style.transform = 'translateX(-105%)';
                sidebar.classList.remove('active');
            }
        });
    }
});
let currentProductId = null;
function openProductModal(card) {
    document.getElementById('modal-product-img').src = card.dataset.image;
    document.getElementById('modal-product-title').textContent = card.dataset.name;
    document.getElementById('modal-product-category').textContent = card.dataset.category;
    document.getElementById('modal-product-designer').textContent = 'Diseñador: ' + card.dataset.designer;
    document.getElementById('modal-product-price').textContent = '🪙 ' + card.dataset.price;
    currentProductId = card.dataset.id;
    const modal = document.getElementById('product-modal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('active'), 10);
}
function closeProductModal() {
    const modal = document.getElementById('product-modal');
    modal.classList.remove('active');
    setTimeout(() => { modal.style.display = 'none'; }, 250);
}
// Modal de éxito
function showSuccessModal(message, redirectUrl) {
    let modal = document.getElementById('shop-success-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'shop-success-modal';
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.width = '100vw';
        modal.style.height = '100vh';
        modal.style.background = 'rgba(10,12,20,0.88)';
        modal.style.display = 'flex';
        modal.style.alignItems = 'center';
        modal.style.justifyContent = 'center';
        modal.style.zIndex = '2000';
        modal.innerHTML = '<div style="background:#181c24;padding:40px 32px;border-radius:16px;box-shadow:0 8px 48px #0008;text-align:center;max-width:340px;width:90vw;color:#fff;font-size:1.2em;">' +
            '<div style="font-size:2.5em;margin-bottom:12px;">✅</div>' +
            '<div id="shop-success-message"></div>' +
        '</div>';
        document.body.appendChild(modal);
    }
    document.getElementById('shop-success-modal').style.display = 'flex';
    document.getElementById('shop-success-message').textContent = message;
    setTimeout(() => {
        window.location.href = redirectUrl;
    }, 1800);
}
// Cerrar modal al hacer click fuera del contenido
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('product-modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeProductModal();
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeProductModal();
    });

    // Comprar producto desde el modal
    const buyBtn = document.getElementById('modal-buy-btn');
    if (buyBtn) {
        buyBtn.addEventListener('click', function() {
            if (!currentProductId) return;
            buyBtn.disabled = true;
            buyBtn.textContent = 'Procesando...';
            fetch('pages/api/shop-item.php?action=purchase', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'item_id=' + encodeURIComponent(currentProductId)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeProductModal();
                    showSuccessModal('¡Compra exitosa! Redirigiendo a tu inventario...', 'inventory');
                } else {
                    if (data.error && data.error.includes('Ya tienes este item')) {
                        showErrorModal('Ya tienes este ítem en tu inventario.');
                    } else {
                        alert(data.error || 'Error al comprar');
                    }
                }
            // Modal de error elegante
            function showErrorModal(message) {
                let modal = document.getElementById('shop-error-modal');
                if (!modal) {
                    modal = document.createElement('div');
                    modal.id = 'shop-error-modal';
                    modal.style.position = 'fixed';
                    modal.style.top = '0';
                    modal.style.left = '0';
                    modal.style.width = '100vw';
                    modal.style.height = '100vh';
                    modal.style.background = 'rgba(10,12,20,0.88)';
                    modal.style.display = 'flex';
                    modal.style.alignItems = 'center';
                    modal.style.justifyContent = 'center';
                    modal.style.zIndex = '2000';
                    modal.innerHTML = '<div style="background:#181c24;padding:40px 32px;border-radius:16px;box-shadow:0 8px 48px #0008;text-align:center;max-width:340px;width:90vw;color:#fff;font-size:1.2em;">' +
                        '<div style="font-size:2.5em;margin-bottom:12px;">⚠️</div>' +
                        '<div id="shop-error-message"></div>' +
                        '<button id="shop-error-close" style="margin-top:18px;padding:8px 28px;background:#ff3570;border:none;border-radius:8px;color:#fff;font-weight:bold;font-size:1em;cursor:pointer;">Cerrar</button>' +
                    '</div>';
                    document.body.appendChild(modal);
                }
                document.getElementById('shop-error-modal').style.display = 'flex';
                document.getElementById('shop-error-message').textContent = message;
                document.getElementById('shop-error-close').onclick = function() {
                    document.getElementById('shop-error-modal').style.display = 'none';
                };
            }
            })
            .catch(() => alert('Error al procesar la compra'))
            .finally(() => {
                buyBtn.disabled = false;
                buyBtn.textContent = 'Comprar';
            });
        });
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
