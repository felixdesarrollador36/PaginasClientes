<?php
/**
 * Liga WOC - Admin Shop Management
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/ShopController.php';

if (!isAdmin()) redirect('dashboard');

$pageTitle = 'Gestionar Tienda';
$page = 'admin';

$shopCtrl = new ShopController();
$db = Database::getInstance();

if (isset($_GET['action']) && isset($_GET['id'])) {
    // Verificar CSRF token para prevenir ataques
    verifyCsrfRequest();
    
    $id = intval($_GET['id']);
    if ($_GET['action'] === 'approve') {
        $shopCtrl->approveItem($id, currentUserId());
        setFlash('success', 'Item aprobado correctamente.');
    } elseif ($_GET['action'] === 'reject') {
        $shopCtrl->rejectItem($id);
        setFlash('success', 'Item rechazado y eliminado.');
    } elseif ($_GET['action'] === 'feature') {
        $db->update("UPDATE shop_items SET is_featured = 1 WHERE id = ?", [$id]);
        setFlash('success', 'Item marcado como destacado.');
    } elseif ($_GET['action'] === 'unfeature') {
        $db->update("UPDATE shop_items SET is_featured = 0 WHERE id = ?", [$id]);
        setFlash('success', 'Item quitado de destacados.');
    }
    redirect('admin/shop');
}

$pendingItems = $shopCtrl->getPendingItems();
$allItems = $db->fetchAll(
    "SELECT si.*, ic.name as category_name, u.username as designer_name 
     FROM shop_items si 
     JOIN item_categories ic ON si.category_id = ic.id 
     JOIN users u ON si.designer_id = u.id 
     ORDER BY si.is_active ASC, si.created_at DESC"
);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">🏪 Gestionar Tienda</h1>
    </div>

    <?php if (!empty($pendingItems)): ?>
    <div class="card mb-4" style="border-left: 4px solid #FFD700;">
        <div class="card-header">
            <h3 class="card-title" style="color:#FFD700;">⏳ Items Pendientes de Aprobación (<?= count($pendingItems) ?>)</h3>
        </div>
        <div style="padding:16px;">
            <?php foreach ($pendingItems as $item): ?>
            <div style="display:flex;align-items:center;gap:16px;padding:12px;background:var(--bg-elevated);border-radius:8px;margin-bottom:12px;">
                <div style="width:60px;height:60px;background:var(--bg-card);border-radius:8px;overflow:hidden;flex-shrink:0;">
                    <img src="<?= url('assets/shop/' . $item['image']) ?>" style="width:100%;height:100%;object-fit:cover;">
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;"><?= htmlspecialchars($item['name']) ?></div>
                    <div style="font-size:0.8rem;color:var(--text-muted);">
                        <?= $item['category_name'] ?> · Por: <?= htmlspecialchars($item['designer_name']) ?> · <?= $item['price_coins'] ?> WOC
                    </div>
                    <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;">
                        <?= htmlspecialchars($item['description'] ?? 'Sin descripción') ?>
                    </div>
                </div>
                <div style="display:flex;gap:8px;">
                    <a href="<?= url('admin/shop?action=approve&id=' . $item['id'] . '&csrf_token=' . csrfToken()) ?>" class="btn btn-sm btn-success" title="Aprobar">✅</a>
                    <a href="<?= url('admin/shop?action=reject&id=' . $item['id'] . '&csrf_token=' . csrfToken()) ?>" class="btn btn-sm btn-danger" title="Rechazar" onclick="return confirm('¿Rechazar este item?')">❌</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📋 Todos los Items</h3>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Imagen</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Diseñador</th>
                        <th>Precio</th>
                        <th>Ventas</th>
                        <th>Estado</th>
                        <th>Destacado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($allItems as $item): ?>
                <tr>
                    <td>
                        <div style="width:40px;height:40px;background:var(--bg-card);border-radius:6px;overflow:hidden;">
                            <img src="<?= url('assets/shop/' . $item['image']) ?>" style="width:100%;height:100%;object-fit:cover;">
                        </div>
                    </td>
                    <td style="font-weight:600;"><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= $item['category_name'] ?></td>
                    <td><?= htmlspecialchars($item['designer_name']) ?></td>
                    <td><?= $item['price_coins'] ?> WOC</td>
                    <td><?= $item['total_sales'] ?></td>
                    <td>
                        <?php if ($item['is_active']): ?>
                            <span class="badge badge-green">Activo</span>
                        <?php else: ?>
                            <span class="badge badge-yellow">Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($item['is_featured']): ?>
                            <span class="badge badge-purple">⭐</span>
                        <?php else: ?>
                            <span style="color:var(--text-muted);">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <?php if (!$item['is_active']): ?>
                                <a href="<?= url('admin/shop?action=approve&id=' . $item['id'] . '&csrf_token=' . csrfToken()) ?>" class="btn btn-sm btn-success" title="Aprobar">✅</a>
                            <?php endif; ?>
                            <?php if ($item['is_featured']): ?>
                                <a href="<?= url('admin/shop?action=unfeature&id=' . $item['id'] . '&csrf_token=' . csrfToken()) ?>" class="btn btn-sm btn-secondary" title="Quitar destacado">⭐</a>
                            <?php else: ?>
                                <a href="<?= url('admin/shop?action=feature&id=' . $item['id'] . '&csrf_token=' . csrfToken()) ?>" class="btn btn-sm btn-secondary" title="Marcar destacado">☆</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
