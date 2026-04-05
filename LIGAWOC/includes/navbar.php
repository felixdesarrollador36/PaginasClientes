<?php
/**
 * Liga WOC - Navigation (Desktop Sidebar + Mobile Nav)
 * Professional SVG icon system — no emojis
 */
$currentPage = $page ?? '';
$isAdminPage = ($currentPage === 'admin');

// Get active season for display
$_activeSeason = Database::getInstance()->fetch("SELECT name FROM seasons WHERE is_active = 1 LIMIT 1");
$_seasonLabel = $_activeSeason ? $_activeSeason['name'] : null;

$isDesigner = false;
if (!isAdmin()) {
    require_once __DIR__ . '/../controllers/ShopController.php';
    $shopCtrl = new ShopController();
    $isDesigner = $shopCtrl->isDesigner(currentUserId());
}

// SVG icon definitions (inline, minimal)
function wocIcon($name, $class = '') {
    $icons = [
        'gamepad'    => '<path d="M6 11h4M8 9v4M15 12h.01M18 10h.01"/><rect x="2" y="6" width="20" height="12" rx="2"/>',
        'home'       => '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'newspaper'  => '<path d="M19 5v14H5V5"/><path d="M3 7h2v12H3z"/><path d="M7 9h6M7 13h8M7 17h4"/>',
        'trophy'     => '<path d="M6 9H4a2 2 0 01-2-2V5h4M18 9h2a2 2 0 002-2V5h-4"/><path d="M6 5a6 6 0 0012 0"/><path d="M12 15v4M8 19h8"/><rect x="6" y="3" width="12" height="2" rx="1"/>',
        'users'      => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>',
        'swords'     => '<path d="M14.5 17.5L3 6V3h3l11.5 11.5"/><path d="M13 19l6-6"/><path d="M16 16l4 4"/><path d="M19.5 6.5L21 3h-3l-11.5 11.5"/><path d="M11 19l-6-6"/><path d="M4 20l4-4"/>',
        'bar-chart'  => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
        'tv'         => '<rect x="2" y="7" width="20" height="15" rx="2" ry="2"/><polyline points="17 2 12 7 7 2"/>',
        'clock'      => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'user'       => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'bell'       => '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>',
        'settings'   => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>',
        'log-out'    => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'shield'     => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'gift'       => '<polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/>',
        'target'     => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
        'star'       => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'award'      => '<circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>',
        'plus'       => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'calendar'   => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'clipboard'  => '<path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>',
        'dollar'     => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>',
        'eye'        => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'file-text'  => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
        'play'       => '<polygon points="5 3 19 12 5 21 5 3"/>',
        'lock'       => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>',
        'rocket'     => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 00-2.91-.09z"/><path d="M12 15l-3-3a22 22 0 012-3.95A12.88 12.88 0 0122 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 01-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
        'arrow-left' => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'info'       => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        'layout'     => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>',
        'shopping-bag' => '<path d="M6 8h12l-1 12H7L6 8z"/><path d="M9 8V6a3 3 0 016 0v2"/>',
        'shopping-cart' => '<circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/><path d="M3 4h2l2.2 10.5a2 2 0 002 1.5h7.8a2 2 0 001.95-1.55L21 8H7"/>',
        'menu'       => '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>',
    ];
    $svg = $icons[$name] ?? '';
    return '<svg class="icon-svg ' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $svg . '</svg>';
}
?>

<!-- Desktop Top Immersive Navigation -->
<header class="topnav-immersive<?= $isDesigner ? ' has-designer-link' : '' ?>">
    <div class="topnav-left">
        <a href="<?= url(isAdmin() ? 'admin' : 'dashboard') ?>" class="topnav-logo">
            <img src="<?= asset('img/logo.png') ?>" alt="Liga WOC Logo" class="topnav-logo-img">
            <span class="topnav-logo-text">Liga WOC</span>
        </a>
        
        <nav class="topnav-links">
            <?php if ($_seasonLabel): ?>
            <div class="season-badge" title="Temporada actual">
                <?= wocIcon('calendar', 'sm') ?>
                <span><?= htmlspecialchars($_seasonLabel) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!isAdmin()): ?>
                <a href="<?= url('dashboard') ?>" class="topnav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="<?= url('news') ?>" class="topnav-link <?= $currentPage === 'news' ? 'active' : '' ?>">Noticias</a>
                <div class="topnav-dropdown-container">
                    <a href="<?= url('tournaments') ?>" class="topnav-link <?= $currentPage === 'tournaments' ? 'active' : '' ?>">Torneos <?= wocIcon('trophy', 'sm') ?></a>
                </div>
                <div class="topnav-dropdown-container">
                    <a href="<?= url('teams') ?>" class="topnav-link <?= $currentPage === 'teams' ? 'active' : '' ?>">Equipos</a>
                    <?php
                    $teamCtrl = new TeamController();
                    $myTeam = $teamCtrl->getUserTeam(currentUserId());
                    if ($myTeam):
                    ?>
                    <div class="topnav-dropdown">
                        <a href="<?= url('teams/view/' . $myTeam['team_id']) ?>"><?= wocIcon('swords', 'sm') ?> Mi Equipo</a>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Competencia en Dropdown -->
                <div class="topnav-dropdown-container">
                    <a href="#" class="topnav-link has-caret <?= in_array($currentPage, ['rankings', 'stream', 'match-history', 'heroes', 'calendar']) ? 'active' : '' ?>">
                        <span>Comunidad</span><span class="caret">▾</span>
                    </a>
                    <div class="topnav-dropdown">
                        <a href="<?= url('rankings') ?>"><?= wocIcon('bar-chart', 'sm') ?> Rankings</a>
                        <a href="<?= url('stream') ?>"><?= wocIcon('tv', 'sm') ?> En Vivo</a>
                        <a href="<?= url('match-history') ?>"><?= wocIcon('clock', 'sm') ?> Historial</a>
                        <a href="<?= url('calendar') ?>"><?= wocIcon('calendar', 'sm') ?> Calendario</a>
                        <a href="<?= url('heroes') ?>"><?= wocIcon('star', 'sm') ?> Héroes MLBB</a>
                    </div>
                </div>
                <a href="<?= url('shop') ?>" class="topnav-link topnav-link--shop <?= $currentPage === 'shop' ? 'active' : '' ?>">🪙 Tienda</a>
                <?php if ($isDesigner): ?>
                <a href="<?= url('designer') ?>" class="topnav-link topnav-link--designer <?= $currentPage === 'designer' ? 'active' : '' ?>">🎨 Designer</a>
                <?php endif; ?>
                <?php if (isModerator()): ?>
                <a href="<?= url('admin/users') ?>" class="topnav-link topnav-link--mod <?= ($action ?? '') === 'users' ? 'active' : '' ?>"><?= wocIcon('shield', 'sm') ?> Mod Panel</a>
                <?php endif; ?>
            <?php else: ?>
                <!-- Admin Topnav Links -->
                <a href="<?= url('admin') ?>" class="topnav-link <?= ($action ?? '') === 'index' || !$action ? 'active' : '' ?>">Dashboard Admin</a>
                <a href="<?= url('admin/news') ?>" class="topnav-link <?= ($action ?? '') === 'news' ? 'active' : '' ?>">Noticias</a>
                <a href="<?= url('admin/tournaments') ?>" class="topnav-link <?= ($action ?? '') === 'tournaments' ? 'active' : '' ?>">Torneos</a>
                <div class="topnav-dropdown-container">
                    <a href="#" class="topnav-link has-caret"><span>Gestión</span><span class="caret">▾</span></a>
                    <div class="topnav-dropdown">
                        <a href="<?= url('admin/users') ?>">Usuarios</a>
                        <a href="<?= url('admin/teams') ?>">Equipos</a>
                        <a href="<?= url('admin/shop') ?>">🏪 Tienda</a>
                        <a href="<?= url('admin/streams') ?>">Streams</a>
                        <a href="<?= url('admin/prizes') ?>">Premios</a>
                        <a href="<?= url('admin/admin_heroes') ?>">Héroes</a>
                        <?php if (isSuperAdmin()): ?>
                            <a href="<?= url('admin/superadmin') ?>">SuperAdmin</a>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="<?= url('dashboard') ?>" class="topnav-link view-site-btn">Ir al Sitio</a>
            <?php endif; ?>
        </nav>
    </div>
    
    <div class="topnav-right">
        <!-- Search -->
        <div class="topnav-search-wrap" id="searchWrap">
            <form method="GET" action="<?= url('search') ?>" id="topnavSearchForm" class="topnav-search-form">
                <input type="text" name="q" placeholder="Buscar..." class="topnav-search-input" id="topnavSearchInput" autocomplete="off">
            </form>
            <button type="button" class="notif-btn topnav-search-btn" id="searchToggleBtn" title="Buscar">
                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </button>
        </div>
        <a href="<?= url('notifications') ?>" class="notif-btn notif-btn-link" id="desktop-notif-btn">
            <?= wocIcon('bell') ?>
            <span class="notif-badge" id="desktop-notif-badge"></span>
        </a>
        <div class="dropdown">
            <div class="user-menu" data-dropdown-toggle>
                <?php if (!empty($_SESSION['user_avatar'])): ?>
                    <img src="<?= UPLOAD_URL . $_SESSION['user_avatar'] ?>" class="user-avatar" alt="">
                <?php else: ?>
                    <div class="user-avatar-placeholder"><?= strtoupper(substr(currentUsername() ?? 'U', 0, 1)) ?></div>
                <?php endif; ?>
                <span class="user-menu-name"><?= currentUsername() ?></span>
            </div>
            <div class="dropdown-menu">
                <a href="<?= url('profile') ?>" class="dropdown-item"><?= wocIcon('user', 'sm') ?> Mi Perfil</a>
                <a href="<?= url('inventory') ?>" class="dropdown-item"><?= wocIcon('shopping-bag', 'sm') ?> Mi Inventario</a>
                <a href="<?= url('buy-coins') ?>" class="dropdown-item"><?= wocIcon('shopping-cart', 'sm') ?> Comprar WOC</a>
                <a href="<?= url('notifications') ?>" class="dropdown-item"><?= wocIcon('bell', 'sm') ?> Notificaciones</a>
                <?php if (isAdmin() && !$isAdminPage): ?>
                    <a href="<?= url('admin') ?>" class="dropdown-item"><?= wocIcon('settings', 'sm') ?> Admin Panel</a>
                <?php elseif (isModerator() && !isAdmin() && !$isAdminPage): ?>
                    <a href="<?= url('admin/users') ?>" class="dropdown-item"><?= wocIcon('shield', 'sm') ?> Panel Moderador</a>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <a href="<?= url('logout') ?>" class="dropdown-item dropdown-item--danger"><?= wocIcon('log-out', 'sm') ?> Cerrar Sesión</a>
            </div>
        </div>
    </div>
</header>

<!-- Mobile Header (Visible only on small screens) -->
<div class="mobile-header">
    <div class="mobile-header-main">
        <a href="<?= url(isAdmin() ? 'admin' : 'dashboard') ?>" class="mobile-logo mobile-logo-link">
            <img src="<?= asset('img/logo.png') ?>" alt="Liga WOC Logo" class="mobile-logo-img"> Liga WOC
        </a>
        <?php if ($_seasonLabel): ?>
        <span class="season-badge season-badge-sm"><?= htmlspecialchars($_seasonLabel) ?></span>
        <?php endif; ?>
    </div>
    <div class="mobile-header-actions">
        <button type="button" class="notif-btn mobile-menu-toggle" id="mobile-menu-toggle" title="Abrir menú" aria-controls="mobile-menu-panel" aria-expanded="false">
            <?= wocIcon('menu') ?>
        </button>
        <!-- Mobile Search Button -->
        <button type="button" class="notif-btn" id="mobile-search-btn" title="Buscar">
            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
        <a href="<?= url('notifications') ?>" class="notif-btn" id="mobile-notif-btn">
            <?= wocIcon('bell') ?>
            <span class="notif-badge" id="mobile-notif-badge"></span>
        </a>
        <a href="<?= url('inventory') ?>" class="notif-btn" title="Mi Inventario">
            <?= wocIcon('shopping-bag') ?>
        </a>
        <a href="<?= url('buy-coins') ?>" class="notif-btn" title="Comprar WOC">
            <?= wocIcon('shopping-cart') ?>
        </a>
        <a href="<?= url('profile') ?>">
            <?php if (!empty($_SESSION['user_avatar'])): ?>
                <img src="<?= UPLOAD_URL . $_SESSION['user_avatar'] ?>" class="user-avatar" alt="">
            <?php else: ?>
                <div class="user-avatar-placeholder"><?= strtoupper(substr(currentUsername() ?? 'U', 0, 1)) ?></div>
            <?php endif; ?>
        </a>
        <a href="<?= url('logout') ?>" class="notif-btn notif-btn--danger" title="Cerrar Sesión">
            <?= wocIcon('log-out') ?>
        </a>
    </div>
</div>

<div class="mobile-menu-panel" id="mobile-menu-panel" hidden>
    <div class="mobile-menu-sheet" role="dialog" aria-label="Menú móvil">
        <div class="mobile-menu-head">
            <span>Menú rápido</span>
            <button type="button" class="mobile-menu-close" id="mobile-menu-close">Cerrar</button>
        </div>
        <div class="mobile-menu-grid">
            <?php if (isAdmin()): ?>
                <a href="<?= url('admin') ?>" class="mobile-menu-link <?= ($currentPage === 'admin' && ((!!($action ?? '')) == false || $action === 'index')) ? 'active' : '' ?>"><?= wocIcon('layout') ?> Panel</a>
                <a href="<?= url('admin/news') ?>" class="mobile-menu-link <?= ($action ?? '') === 'news' ? 'active' : '' ?>"><?= wocIcon('newspaper') ?> Noticias</a>
                <a href="<?= url('admin/tournaments') ?>" class="mobile-menu-link <?= ($action ?? '') === 'tournaments' ? 'active' : '' ?>"><?= wocIcon('trophy') ?> Torneos</a>
                <a href="<?= url('admin/users') ?>" class="mobile-menu-link <?= ($action ?? '') === 'users' ? 'active' : '' ?>"><?= wocIcon('users') ?> Usuarios</a>
                <a href="<?= url('admin/teams') ?>" class="mobile-menu-link <?= ($action ?? '') === 'teams' ? 'active' : '' ?>"><?= wocIcon('swords') ?> Equipos</a>
                <a href="<?= url('admin/shop') ?>" class="mobile-menu-link <?= ($action ?? '') === 'shop' ? 'active' : '' ?>"><?= wocIcon('shopping-cart') ?> Tienda</a>
                <a href="<?= url('admin/streams') ?>" class="mobile-menu-link <?= ($action ?? '') === 'streams' ? 'active' : '' ?>"><?= wocIcon('tv') ?> Streams</a>
                <a href="<?= url('admin/prizes') ?>" class="mobile-menu-link <?= ($action ?? '') === 'prizes' ? 'active' : '' ?>"><?= wocIcon('gift') ?> Premios</a>
                <a href="<?= url('admin/admin_heroes') ?>" class="mobile-menu-link"><?= wocIcon('star') ?> Héroes</a>
                <?php if (isSuperAdmin()): ?>
                <a href="<?= url('admin/superadmin') ?>" class="mobile-menu-link <?= ($action ?? '') === 'superadmin' ? 'active' : '' ?>"><?= wocIcon('shield') ?> SuperAdmin</a>
                <?php endif; ?>
                <a href="<?= url('dashboard') ?>" class="mobile-menu-link"><?= wocIcon('home') ?> Ir al Sitio</a>
            <?php elseif (isModerator()): ?>
                <a href="<?= url('admin/users') ?>" class="mobile-menu-link <?= ($action ?? '') === 'users' ? 'active' : '' ?>"><?= wocIcon('shield') ?> Panel Moderador</a>
                <a href="<?= url('dashboard') ?>" class="mobile-menu-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>"><?= wocIcon('home') ?> Inicio</a>
                <a href="<?= url('news') ?>" class="mobile-menu-link <?= $currentPage === 'news' ? 'active' : '' ?>"><?= wocIcon('newspaper') ?> Noticias</a>
                <a href="<?= url('tournaments') ?>" class="mobile-menu-link <?= $currentPage === 'tournaments' ? 'active' : '' ?>"><?= wocIcon('trophy') ?> Torneos</a>
                <a href="<?= url('teams') ?>" class="mobile-menu-link <?= $currentPage === 'teams' ? 'active' : '' ?>"><?= wocIcon('users') ?> Equipos</a>
                <a href="<?= url('rankings') ?>" class="mobile-menu-link <?= $currentPage === 'rankings' ? 'active' : '' ?>"><?= wocIcon('bar-chart') ?> Rankings</a>
                <a href="<?= url('stream') ?>" class="mobile-menu-link <?= $currentPage === 'stream' ? 'active' : '' ?>"><?= wocIcon('tv') ?> En vivo</a>
                <a href="<?= url('match-history') ?>" class="mobile-menu-link <?= $currentPage === 'match-history' ? 'active' : '' ?>"><?= wocIcon('clock') ?> Historial</a>
                <a href="<?= url('calendar') ?>" class="mobile-menu-link <?= $currentPage === 'calendar' ? 'active' : '' ?>"><?= wocIcon('calendar') ?> Calendario</a>
                <a href="<?= url('heroes') ?>" class="mobile-menu-link <?= $currentPage === 'heroes' ? 'active' : '' ?>"><?= wocIcon('star') ?> Héroes</a>
                <a href="<?= url('shop') ?>" class="mobile-menu-link <?= $currentPage === 'shop' ? 'active' : '' ?>"><?= wocIcon('shopping-cart') ?> Tienda</a>
                <a href="<?= url('notifications') ?>" class="mobile-menu-link <?= $currentPage === 'notifications' ? 'active' : '' ?>"><?= wocIcon('bell') ?> Notificaciones</a>
                <a href="<?= url('inventory') ?>" class="mobile-menu-link <?= $currentPage === 'inventory' ? 'active' : '' ?>"><?= wocIcon('shopping-bag') ?> Inventario</a>
                <a href="<?= url('buy-coins') ?>" class="mobile-menu-link <?= $currentPage === 'buy-coins' ? 'active' : '' ?>"><?= wocIcon('dollar') ?> Comprar WOC</a>
                <a href="<?= url('profile') ?>" class="mobile-menu-link <?= $currentPage === 'profile' ? 'active' : '' ?>"><?= wocIcon('user') ?> Perfil</a>
            <?php else: ?>
                <a href="<?= url('dashboard') ?>" class="mobile-menu-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>"><?= wocIcon('home') ?> Inicio</a>
                <a href="<?= url('news') ?>" class="mobile-menu-link <?= $currentPage === 'news' ? 'active' : '' ?>"><?= wocIcon('newspaper') ?> Noticias</a>
                <a href="<?= url('tournaments') ?>" class="mobile-menu-link <?= $currentPage === 'tournaments' ? 'active' : '' ?>"><?= wocIcon('trophy') ?> Torneos</a>
                <a href="<?= url('teams') ?>" class="mobile-menu-link <?= $currentPage === 'teams' ? 'active' : '' ?>"><?= wocIcon('users') ?> Equipos</a>
                <a href="<?= url('rankings') ?>" class="mobile-menu-link <?= $currentPage === 'rankings' ? 'active' : '' ?>"><?= wocIcon('bar-chart') ?> Rankings</a>
                <a href="<?= url('stream') ?>" class="mobile-menu-link <?= $currentPage === 'stream' ? 'active' : '' ?>"><?= wocIcon('tv') ?> En vivo</a>
                <a href="<?= url('match-history') ?>" class="mobile-menu-link <?= $currentPage === 'match-history' ? 'active' : '' ?>"><?= wocIcon('clock') ?> Historial</a>
                <a href="<?= url('calendar') ?>" class="mobile-menu-link <?= $currentPage === 'calendar' ? 'active' : '' ?>"><?= wocIcon('calendar') ?> Calendario</a>
                <a href="<?= url('heroes') ?>" class="mobile-menu-link <?= $currentPage === 'heroes' ? 'active' : '' ?>"><?= wocIcon('star') ?> Héroes</a>
                <a href="<?= url('shop') ?>" class="mobile-menu-link <?= $currentPage === 'shop' ? 'active' : '' ?>"><?= wocIcon('shopping-cart') ?> Tienda</a>
                <?php if ($isDesigner): ?>
                <a href="<?= url('designer') ?>" class="mobile-menu-link <?= $currentPage === 'designer' ? 'active' : '' ?>"><?= wocIcon('rocket') ?> Designer</a>
                <?php endif; ?>
                <a href="<?= url('notifications') ?>" class="mobile-menu-link <?= $currentPage === 'notifications' ? 'active' : '' ?>"><?= wocIcon('bell') ?> Notificaciones</a>
                <a href="<?= url('inventory') ?>" class="mobile-menu-link <?= $currentPage === 'inventory' ? 'active' : '' ?>"><?= wocIcon('shopping-bag') ?> Inventario</a>
                <a href="<?= url('buy-coins') ?>" class="mobile-menu-link <?= $currentPage === 'buy-coins' ? 'active' : '' ?>"><?= wocIcon('dollar') ?> Comprar WOC</a>
                <a href="<?= url('profile') ?>" class="mobile-menu-link <?= $currentPage === 'profile' ? 'active' : '' ?>"><?= wocIcon('user') ?> Perfil</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Mobile Bottom Nav -->
<nav class="mobile-nav">
    <?php if (isAdmin()): ?>
        <a href="<?= url('admin') ?>" class="mobile-nav-item <?= ($currentPage === 'admin' && ((!!($action ?? '')) == false || $action === 'index')) ? 'active' : '' ?>">
            <?= wocIcon('layout') ?>
            <span>Panel</span>
        </a>
        <a href="<?= url('admin/tournaments') ?>" class="mobile-nav-item <?= ($action ?? '') === 'tournaments' ? 'active' : '' ?>">
            <?= wocIcon('trophy') ?>
            <span>Torneos</span>
        </a>
        <a href="<?= url('admin/users') ?>" class="mobile-nav-item <?= ($action ?? '') === 'users' ? 'active' : '' ?>">
            <?= wocIcon('users') ?>
            <span>Usuarios</span>
        </a>
        <a href="<?= url('admin/teams') ?>" class="mobile-nav-item <?= ($action ?? '') === 'teams' ? 'active' : '' ?>">
            <?= wocIcon('swords') ?>
            <span>Equipos</span>
        </a>
    <?php elseif (isModerator()): ?>
        <a href="<?= url('dashboard') ?>" class="mobile-nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <?= wocIcon('home') ?>
            <span>Inicio</span>
        </a>
        <a href="<?= url('tournaments') ?>" class="mobile-nav-item <?= $currentPage === 'tournaments' ? 'active' : '' ?>">
            <?= wocIcon('trophy') ?>
            <span>Torneos</span>
        </a>
        <a href="<?= url('teams') ?>" class="mobile-nav-item <?= $currentPage === 'teams' ? 'active' : '' ?>">
            <?= wocIcon('users') ?>
            <span>Equipos</span>
        </a>
        <a href="<?= url('rankings') ?>" class="mobile-nav-item <?= $currentPage === 'rankings' ? 'active' : '' ?>">
            <?= wocIcon('bar-chart') ?>
            <span>Ranking</span>
        </a>
        <a href="<?= url('admin/users') ?>" class="mobile-nav-item <?= ($action ?? '') === 'users' ? 'active' : '' ?>">
            <?= wocIcon('shield') ?>
            <span>Mod</span>
        </a>
    <?php else: ?>
        <a href="<?= url('dashboard') ?>" class="mobile-nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <?= wocIcon('home') ?>
            <span>Inicio</span>
        </a>
        <a href="<?= url('tournaments') ?>" class="mobile-nav-item <?= $currentPage === 'tournaments' ? 'active' : '' ?>">
            <?= wocIcon('trophy') ?>
            <span>Torneos</span>
        </a>
        <a href="<?= url('teams') ?>" class="mobile-nav-item <?= $currentPage === 'teams' ? 'active' : '' ?>">
            <?= wocIcon('users') ?>
            <span>Equipos</span>
        </a>
        <a href="<?= url('rankings') ?>" class="mobile-nav-item <?= $currentPage === 'rankings' ? 'active' : '' ?>">
            <?= wocIcon('bar-chart') ?>
            <span>Ranking</span>
        </a>
        <a href="<?= url('heroes') ?>" class="mobile-nav-item <?= $currentPage === 'heroes' ? 'active' : '' ?>">
            <?= wocIcon('star') ?>
            <span>Héroes</span>
        </a>
    <?php endif; ?>
    <a href="<?= url('profile') ?>" class="mobile-nav-item <?= $currentPage === 'profile' ? 'active' : '' ?>">
        <?= wocIcon('user') ?>
        <span>Perfil</span>
    </a>
</nav>
