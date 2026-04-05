<?php
if (!isLoggedIn()) redirect('login');

$shopCtrl = new ShopController();
$userId = currentUserId();

if (!$shopCtrl->isDesigner($userId)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
        $portfolio = sanitize($_POST['portfolio'] ?? '');
        $result = $shopCtrl->applyAsDesigner($userId, $portfolio);
        if ($result['success']) {
            setFlash('success', 'Tu solicitud ha sido enviada. Te avisaremos cuando sea revisada.');
        } else {
            setFlash('error', $result['error']);
        }
        redirect('designer');
    }
    
    $pageTitle = 'Become a Designer';
    $pageCss = 'designer';
    require_once __DIR__ . '/../includes/header.php';
    require_once __DIR__ . '/../includes/navbar.php';
    ?>
    <div class="app-wrapper">
    <div class="main-content">
        <div class="card designer-apply-card">
            <div class="designer-apply-icon">🎨</div>
            <h2>Conviértete en Diseñador</h2>
            <p class="designer-apply-copy">Crea y vende tus propios marcos, portadas y avatares en la tienda de WOC.</p>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Portfolio / Enlace a tus trabajos</label>
                    <textarea name="portfolio" class="form-control" rows="4" placeholder="Muestra ejemplos de tu trabajo..." required></textarea>
                </div>
                <button type="submit" name="apply" class="btn btn-primary designer-submit-btn">📨 Enviar Solicitud</button>
            </form>
        </div>
    </div>
    </div>
    <?php require_once __DIR__ . '/../includes/footer.php'; exit;
}

$pageTitle = 'Panel de Diseñador';
$pageCss = 'designer';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$stats = $shopCtrl->getDesignerStats($userId);
$categories = $shopCtrl->getCategories() ?: [];
$myItems = [];
if ($userId) {
    $myItems = $shopCtrl->db->fetchAll(
        "SELECT si.*, ic.name as category_name FROM shop_items si 
         JOIN item_categories ic ON si.category_id = ic.id 
         WHERE si.designer_id = ? ORDER BY si.created_at DESC",
        [$userId]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_item'])) {
    $categoryId = intval($_POST['category_id']);
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $price = intval($_POST['price']);

    $selectedCategory = null;
    foreach ($categories as $cat) {
        if ((int)($cat['id'] ?? 0) === $categoryId) {
            $selectedCategory = $cat;
            break;
        }
    }

    if (!$selectedCategory) {
        setFlash('error', 'Categoría inválida.');
        redirect('designer');
    }

    $categorySlug = strtolower((string)($selectedCategory['slug'] ?? ''));
    $categoryType = strtolower((string)($selectedCategory['type'] ?? ''));
    $categoryName = strtolower((string)($selectedCategory['name'] ?? ''));
    $isPortadaCategory = strpos($categorySlug, 'portada') !== false
        || strpos($categoryType, 'portada') !== false
        || strpos($categoryName, 'portada') !== false;
    
    $uploadDir = __DIR__ . '/../assets/shop/';
    
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    
    if (isset($_FILES['image'])) {
        require_once __DIR__ . '/../libs/FileValidator.php';
        
        // Validar imagen con magic bytes
        $validation = FileValidator::validateImage($_FILES['image'], 5 * 1024 * 1024);
        
        if ($validation['success']) {
            $fileInfo = $validation['file'];

            if ($isPortadaCategory) {
                $requiredPortadaWidth = 1920;
                $requiredPortadaHeight = 500;
                $imageDimensions = @getimagesize($fileInfo['tmp_name']);

                if ($imageDimensions === false) {
                    setFlash('error', 'No se pudieron leer las dimensiones de la portada.');
                    redirect('designer');
                }

                $imageWidth = (int)$imageDimensions[0];
                $imageHeight = (int)$imageDimensions[1];

                if ($imageWidth !== $requiredPortadaWidth || $imageHeight !== $requiredPortadaHeight) {
                    setFlash('error', 'Para categoría Portada, la imagen debe medir exactamente 1920 x 500 px. Imagen recibida: ' . $imageWidth . ' x ' . $imageHeight . ' px.');
                    redirect('designer');
                }
            }
            
            // Mover archivo al directorio de upload
            $moveResult = FileValidator::moveToPrivateStorage(
                $fileInfo['tmp_name'],
                $fileInfo['safe_name'],
                $uploadDir
            );
            
            if ($moveResult['success']) {
                // Usar nombre seguro en BD
                $shopCtrl->createItem($userId, $categoryId, $name, $description, $fileInfo['safe_name'], $price);
                
                setFlash('success', 'Item creado exitosamente.');
                redirect('shop');
            } else {
                setFlash('error', 'Error al subir imagen: ' . $moveResult['error']);
                redirect('designer');
            }
        } else {
            setFlash('error', 'Error en validación de imagen: ' . $validation['error']);
            redirect('designer');
        }
    } else {
        setFlash('error', 'Por favor sube una imagen.');
        redirect('designer');
    }
}
?>
<div class="app-wrapper">
<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">🎨 Panel de Diseñador</h1>
    </div>

    <div class="grid grid-3 mb-4 designer-stats-grid">
        <div class="card designer-stat-card">
            <div class="designer-stat-icon">📦</div>
            <div class="designer-stat-value"><?= $stats['total_items'] ?? 0 ?></div>
            <div class="designer-stat-label">Total Items</div>
        </div>
        <div class="card designer-stat-card">
            <div class="designer-stat-icon">💰</div>
            <div class="designer-stat-value"><?= $stats['total_sales'] ?? 0 ?></div>
            <div class="designer-stat-label">Ventas Totales</div>
        </div>
        <div class="card designer-stat-card">
            <div class="designer-stat-icon">📈</div>
            <div class="designer-stat-value"><?= number_format($stats['monthly_revenue'] ?? 0) ?></div>
            <div class="designer-stat-label">Ingresos del Mes</div>
        </div>
    </div>

    <div class="grid grid-2 designer-main-grid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">➕ Crear Nuevo Item</h3>
            </div>
            <form method="POST" enctype="multipart/form-data" class="designer-item-form">
                <div class="form-group">
                    <label class="form-label">Categoría</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($categories as $cat): ?>
                        <option
                            value="<?= $cat['id'] ?>"
                            data-category-name="<?= htmlspecialchars(strtolower((string)($cat['name'] ?? '')), ENT_QUOTES) ?>"
                            data-category-slug="<?= htmlspecialchars(strtolower((string)($cat['slug'] ?? '')), ENT_QUOTES) ?>"
                            data-category-type="<?= htmlspecialchars(strtolower((string)($cat['type'] ?? '')), ENT_QUOTES) ?>"
                        >
                            <?= $cat['name'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Nombre del Item</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Precio (WOC Coins)</label>
                    <input type="number" name="price" class="form-control" min="1" value="100" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Imagen</label>
                    <input type="file" name="image" class="form-control" accept="image/*" required>
                    <small class="designer-portada-hint" data-portada-hint hidden>Si seleccionas categoría Portada, la imagen debe ser exactamente 1920 x 500 px.</small>
                </div>
                <button type="submit" name="create_item" class="btn btn-primary designer-submit-btn">➕ Crear Item</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📋 Mis Items</h3>
            </div>
            <div class="designer-items-list">
                <?php if (empty($myItems)): ?>
                <p class="designer-empty-copy">No has creado ningún item aún.</p>
                <?php else: ?>
                <?php foreach ($myItems as $item): ?>
                <div class="designer-item-row">
                    <div class="designer-item-thumb-wrap">
                        <img src="<?= url('assets/shop/' . $item['image']) ?>" class="designer-item-thumb">
                    </div>
                    <div class="designer-item-meta">
                        <div class="designer-item-name"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="designer-item-subtext"><?= $item['category_name'] ?> · <?= $item['price_coins'] ?> WOC</div>
                    </div>
                    <div>
                        <?php if ($item['is_active']): ?>
                        <span class="badge badge-green">✓ Activo</span>
                        <?php else: ?>
                        <span class="badge badge-yellow">⏳ Pendiente</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
