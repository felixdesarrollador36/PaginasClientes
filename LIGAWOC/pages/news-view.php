<?php
$page = 'news';
$pageCss = 'news-view';
$slug = $_GET['slug'] ?? '';
$newsCtrl = new NewsController();
$article = $newsCtrl->getBySlug($slug);
if (!$article) { redirect('news'); }
$pageTitle = $article['title'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content news-view-main">
    <a href="<?= url('news') ?>" class="news-view-back-link">← Volver a Noticias</a>

    <article class="card news-view-article">
        <?php if ($article['image']): ?>
        <img src="<?= UPLOAD_URL . $article['image'] ?>" class="news-view-hero" alt="<?= htmlspecialchars($article['title']) ?>">
        <?php endif; ?>

        <?php if ($article['category_name']): ?>
        <span class="news-card-category news-view-category" data-category-color="<?= htmlspecialchars($article['category_color'], ENT_QUOTES) ?>">
            <?= $article['category_name'] ?>
        </span>
        <?php endif; ?>

        <h1 class="news-view-title">
            <?= htmlspecialchars($article['title']) ?>
        </h1>

        <div class="news-view-meta">
            <span>Por <strong class="news-view-author"><?= $article['author_name'] ?></strong></span>
            <span>•</span>
            <span><?= date('d M Y, H:i', strtotime($article['created_at'])) ?></span>
            <span>•</span>
            <span>👁 <?= $article['views'] ?> vistas</span>
        </div>

        <div class="news-view-content">
            <?= nl2br(htmlspecialchars($article['content'])) ?>
        </div>
    </article>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
