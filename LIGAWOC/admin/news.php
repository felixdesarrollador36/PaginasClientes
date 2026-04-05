<?php
/**
 * Liga WOC - Admin News Management
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/NewsController.php';
if (!isAdmin()) redirect('dashboard');
$pageTitle = 'Gestionar Noticias';
$page = 'admin';

$newsCtrl = new NewsController();
$categories = $newsCtrl->getCategories();

// Handle delete
if (isset($_GET['delete'])) {
    verifyCsrfRequest();
    $newsCtrl->delete(intval($_GET['delete']));
}

// Handle create/edit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['news_id']) && $_POST['news_id']) {
        $newsCtrl->update(intval($_POST['news_id']));
    } else {
        $newsCtrl->create();
    }
}

$currentPageNum = max(1, intval($_GET['p'] ?? 1));
$result = $newsCtrl->getAll($currentPageNum);
$editArticle = null;
if (isset($_GET['edit'])) {
    $db = Database::getInstance();
    $editArticle = $db->fetch("SELECT * FROM news WHERE id = ?", [intval($_GET['edit'])]);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:start;">
        <div><h1 class="page-title">📰 Gestionar Noticias</h1></div>
        <button class="btn btn-primary" onclick="document.getElementById('news-form').style.display=document.getElementById('news-form').style.display==='none'?'block':'none'">
            ➕ <?= $editArticle ? 'Editando' : 'Nueva Noticia' ?>
        </button>
    </div>

    <!-- Create/Edit Form -->
    <div class="card mb-3" id="news-form" style="display:<?= $editArticle ? 'block' : 'none' ?>;">
        <form method="POST" action="<?= url('admin/news') ?>" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="news_id" value="<?= $editArticle['id'] ?? '' ?>">
            
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label class="form-label">Título *</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($editArticle['title'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Categoría</label>
                    <select name="category_id" class="form-control">
                        <option value="">Sin categoría</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($editArticle['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Extracto</label>
                <input type="text" name="excerpt" class="form-control" value="<?= htmlspecialchars($editArticle['excerpt'] ?? '') ?>" placeholder="Resumen corto...">
            </div>

            <div class="form-group">
                <label class="form-label">Contenido *</label>
                <textarea name="content" class="form-control" rows="8" required><?= htmlspecialchars($editArticle['content'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Imagen</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha de Publicación</label>
                    <input type="datetime-local" name="publish_date" class="form-control" value="<?= $editArticle ? date('Y-m-d\TH:i', strtotime($editArticle['publish_date'] ?? 'now')) : '' ?>">
                </div>
            </div>

            <div style="display:flex;gap:20px;margin-bottom:16px;">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="checkbox" name="is_published" <?= ($editArticle['is_published'] ?? 0) ? 'checked' : '' ?> style="accent-color:var(--electric-indigo);"> Publicar
                </label>
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="checkbox" name="is_featured" <?= ($editArticle['is_featured'] ?? 0) ? 'checked' : '' ?> style="accent-color:var(--electric-indigo);"> Destacar
                </label>
            </div>

            <button type="submit" class="btn btn-primary">💾 <?= $editArticle ? 'Actualizar' : 'Crear Noticia' ?></button>
            <?php if ($editArticle): ?>
            <a href="<?= url('admin/news') ?>" class="btn btn-secondary">Cancelar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- News List -->
    <div class="card">
        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>Título</th><th>Categoría</th><th>Autor</th><th>Estado</th><th>Vistas</th><th>Fecha</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($result['news'] as $article): ?>
                <tr>
                    <td style="max-width:200px;"><span style="font-weight:600;"><?= htmlspecialchars($article['title']) ?></span></td>
                    <td><span class="badge badge-purple"><?= $article['category_name'] ?? 'Sin cat.' ?></span></td>
                    <td style="font-size:0.8rem;"><?= $article['author_name'] ?></td>
                    <td>
                        <?php if ($article['is_published']): ?><span class="badge badge-green">Publicado</span>
                        <?php else: ?><span class="badge badge-yellow">Borrador</span><?php endif; ?>
                        <?php if ($article['is_featured']): ?><span class="badge badge-blue">⭐</span><?php endif; ?>
                    </td>
                    <td><?= $article['views'] ?></td>
                    <td style="font-size:0.8rem;color:var(--text-muted);"><?= date('d M Y', strtotime($article['created_at'])) ?></td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <a href="<?= url('admin/news?edit=' . $article['id']) ?>" class="btn btn-sm btn-secondary">✏️</a>
                            <a href="<?= url('admin/news?delete=' . $article['id'] . '&csrf_token=' . csrfToken()) ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar esta noticia?')">🗑️</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
