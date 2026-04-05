<?php
$pageTitle = 'Noticias';
$page = 'news';
$pageCss = 'news';
$newsCtrl = new NewsController();
$category = $_GET['category'] ?? null;
$currentPageNum = max(1, intval($_GET['p'] ?? 1));
$result = $newsCtrl->getPublished($currentPageNum, $category);
$categories = $newsCtrl->getCategories();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content news-main">
    <div class="page-header">
        <h1 class="page-title">📰 Noticias</h1>
        <p class="page-subtitle">Las últimas novedades de Liga WOC y Mobile Legends</p>
    </div>

    <!-- Category Filter -->
    <div class="news-filter-bar">
        <a href="<?= url('news') ?>" class="btn btn-sm news-filter-link <?= !$category ? 'btn-primary' : 'btn-secondary' ?>">Todas</a>
        <?php foreach ($categories as $cat): ?>
        <a href="<?= url('news?category=' . $cat['slug']) ?>" class="btn btn-sm news-filter-link news-filter-link--category <?= $category === $cat['slug'] ? 'btn-primary' : 'btn-secondary' ?>" data-category-color="<?= htmlspecialchars($cat['color'], ENT_QUOTES) ?>">
            <?= htmlspecialchars($cat['name']) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($result['news'])): ?>
    <div class="grid grid-3 news-grid">
        <?php foreach ($result['news'] as $article): ?>
        <a href="<?= url('news/view/' . $article['slug']) ?>" class="news-card news-card-link">
            <?php if ($article['image']): ?>
                <div class="news-card-media"><img src="<?= UPLOAD_URL . $article['image'] ?>" class="news-card-img" alt=""></div>
            <?php else: ?>
                <div class="news-card-img-placeholder news-card-img-placeholder--page">📰</div>
            <?php endif; ?>
            <div class="news-card-body news-card-body--page">
                <?php if ($article['category_name']): ?>
                <span class="news-card-category news-card-category--page" data-category-color="<?= htmlspecialchars($article['category_color'], ENT_QUOTES) ?>">
                    <?= $article['category_name'] ?>
                </span>
                <?php endif; ?>
                <h3 class="news-card-title news-card-title--page"><?= htmlspecialchars($article['title']) ?></h3>
                <p class="news-card-excerpt news-card-excerpt--page"><?= htmlspecialchars($article['excerpt'] ?: substr(strip_tags($article['content']), 0, 150)) ?></p>
                <div class="news-card-meta news-card-meta--page">
                    <span class="news-card-meta-item"><i class="fas fa-pencil-alt news-card-meta-icon"></i>Por <?= $article['author_name'] ?></span>
                    <span class="news-card-meta-item"><i class="fas fa-clock news-card-meta-icon"></i><?= timeAgo($article['created_at']) ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($result['pages'] > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
        <a href="<?= url('news?p=' . $i . ($category ? '&category=' . $category : '')) ?>" class="page-link <?= $i == $currentPageNum ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state">
        <div class="empty-state-icon">📰</div>
        <h3 class="empty-state-title">No hay noticias</h3>
        <p>Vuelve pronto para ver las últimas novedades</p>
    </div>
    <?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
