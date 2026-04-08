<?php
/**
 * Liga WOC - Page Header
 * Include at the top of every page
 */
$currentPage = $page ?? '';
$pageSlug = preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) $currentPage));
$pageSlug = trim($pageSlug, '-');
$pageSlug = $pageSlug !== '' ? $pageSlug : 'default';

$pageStyle = $pageCss ?? $currentPage;
$pageStyleSlug = preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) $pageStyle));
$pageStyleSlug = trim($pageStyleSlug, '-');
$pageStyleSlug = $pageStyleSlug !== '' ? $pageStyleSlug : 'default';

$mainCssPath = __DIR__ . '/../assets/css/main.css';
$esportsCssPath = __DIR__ . '/../assets/css/esports.css';
$layoutNavbarCssPath = __DIR__ . '/../assets/css/layout/navbar.css';
$pageCssPath = __DIR__ . '/../assets/css/pages/' . $pageStyleSlug . '.css';

$mainCssVersion = file_exists($mainCssPath) ? filemtime($mainCssPath) : time();
$esportsCssVersion = file_exists($esportsCssPath) ? filemtime($esportsCssPath) : time();
$layoutNavbarCssVersion = file_exists($layoutNavbarCssPath) ? filemtime($layoutNavbarCssPath) : null;
$pageCssVersion = file_exists($pageCssPath) ? filemtime($pageCssPath) : null;

// --- BEGIN PROFILE COMPLETION MIDDLEWARE ---
if (isLoggedIn() && !isAdmin() && !isSuperAdmin() && $currentPage !== 'complete-profile' && $currentPage !== 'logout' && $currentPage !== 'shop') {
    $dbInstance = Database::getInstance();
    $uCheck = $dbInstance->fetch("SELECT whatsapp, phone_brand, discord FROM users WHERE id = ?", [currentUserId()]);
    if ($uCheck) {
        if (empty($uCheck['whatsapp']) || empty($uCheck['phone_brand']) || empty($uCheck['discord'])) {
            redirect('complete-profile');
            exit;
        }
    }
}
// --- END PROFILE COMPLETION MIDDLEWARE ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= SITE_DESCRIPTION ?>">
    <meta name="theme-color" content="#6e41ff">

    <!-- Open Graph / Social Media -->
    <?php $ogUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ligawocdominicana.com') . BASE_URL; ?>
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $ogUrl ?>">
    <meta property="og:title" content="<?= isset($pageTitle) ? $pageTitle . ' | ' . SITE_NAME : SITE_NAME ?>">
    <meta property="og:description" content="<?= SITE_DESCRIPTION ?>">
    <meta property="og:image" content="<?= $ogUrl ?>assets/img/og-image.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="<?= SITE_NAME ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= isset($pageTitle) ? $pageTitle . ' | ' . SITE_NAME : SITE_NAME ?>">
    <meta name="twitter:description" content="<?= SITE_DESCRIPTION ?>">
    <meta name="twitter:image" content="<?= $ogUrl ?>assets/img/og-image.png">
    <title><?= isset($pageTitle) ? $pageTitle . ' | ' . SITE_NAME : SITE_NAME ?></title>
    <!-- Google Fonts for Esports Theme -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="<?= asset('css/main.css?v=' . $mainCssVersion) ?>">
    <link rel="stylesheet" href="<?= asset('css/esports.css?v=' . $esportsCssVersion) ?>"> <!-- New Theme Override -->
    <?php if ($layoutNavbarCssVersion !== null): ?>
    <link rel="stylesheet" href="<?= asset('css/layout/navbar.css?v=' . $layoutNavbarCssVersion) ?>">
    <?php endif; ?>
    <?php if ($pageCssVersion !== null): ?>
    <link rel="stylesheet" href="<?= asset('css/pages/' . $pageStyleSlug . '.css?v=' . $pageCssVersion) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= asset('css/heroes-responsive.css') ?>">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect rx='20' width='100' height='100' fill='%236e41ff'/><path d='M30 65V40a5 5 0 015-5h30a5 5 0 015 5v25M25 55h50M40 35v-5a10 10 0 0120 0v5' stroke='white' stroke-width='6' fill='none' stroke-linecap='round'/></svg>">
    <!-- PWA -->
    <link rel="manifest" href="<?= BASE_URL ?>manifest.json">
    <!-- Inject BASE_URL server-side for reliable JS use -->
    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body class="page page-<?= htmlspecialchars($pageSlug) ?> page-style-<?= htmlspecialchars($pageStyleSlug) ?>">
<div class="bg-grid-overlay" aria-hidden="true"></div>
<?php $flash = getFlash(); if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>" id="flash-alert" style="position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:9999;min-width:300px;max-width:90%;">
        <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <?php if ($flash['type'] === 'success'): ?>
            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        <?php elseif ($flash['type'] === 'error'): ?>
            <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
        <?php elseif ($flash['type'] === 'warning'): ?>
            <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        <?php else: ?>
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
        <?php endif; ?>
        </svg>
        <span><?= $flash['message'] ?></span>
    </div>
    <script>setTimeout(() => { document.getElementById('flash-alert')?.remove(); }, 5000);</script>
<?php endif; ?>
</body>
</html>
