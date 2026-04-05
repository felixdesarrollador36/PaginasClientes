<?php
/**
 * Liga WOC — Buscador Global
 */
$pageTitle = 'Búsqueda';
$pageCss = 'search';
$page = 'search';
$db = Database::getInstance();
$q = trim($_GET['q'] ?? '');
$results = ['players' => [], 'teams' => [], 'news' => []];

if (strlen($q) >= 2) {
    $like = '%' . $q . '%';
    $results['players'] = $db->fetchAll(
        "SELECT u.id, u.username, u.ml_nickname, u.avatar, u.role,
                t.name as team_name, t.tag as team_tag, tm.team_id
         FROM users u
         LEFT JOIN team_members tm ON u.id = tm.user_id
         LEFT JOIN teams t ON tm.team_id = t.id AND t.is_active = 1
         WHERE (u.username LIKE ? OR u.ml_nickname LIKE ?)
         LIMIT 10",
        [$like, $like]
    );

    $results['teams'] = $db->fetchAll(
        "SELECT t.id, t.name, t.tag, t.logo,
                COUNT(tm.user_id) as member_count,
                u.username as captain_name
         FROM teams t
         LEFT JOIN team_members tm ON t.id = tm.team_id
         LEFT JOIN users u ON t.captain_id = u.id
         WHERE (t.name LIKE ? OR t.tag LIKE ?) AND t.is_active = 1
         GROUP BY t.id LIMIT 10",
        [$like, $like]
    );

    $results['news'] = $db->fetchAll(
        "SELECT n.id, n.title, n.slug, n.excerpt, n.image, n.created_at,
                nc.name as category_name
         FROM news n
         LEFT JOIN news_categories nc ON n.category_id = nc.id
         WHERE (n.title LIKE ? OR n.excerpt LIKE ?)
           AND n.is_published = 1 AND n.publish_date <= NOW()
         ORDER BY n.created_at DESC LIMIT 8",
        [$like, $like]
    );
}

$totalResults = count($results['players']) + count($results['teams']) + count($results['news']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content">

    <div class="page-header">
        <h1 class="page-title">🔍 Búsqueda</h1>
        <p class="page-subtitle">Busca jugadores, equipos y noticias</p>
    </div>

    <!-- Search Form -->
    <form method="GET" action="<?= url('search') ?>" class="search-form">
        <div class="search-input-row">
            <input type="text"
                   name="q"
                   value="<?= htmlspecialchars($q) ?>"
                   placeholder="Buscar jugadores, equipos, noticias..."
                   class="form-control"
                   id="searchQuery"
                   autofocus>
            <button type="submit" class="btn btn-primary search-submit-btn">
                <svg class="icon-svg sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                Buscar
            </button>
        </div>
    </form>

    <?php if ($q !== '' && strlen($q) < 2): ?>
    <div class="alert alert-warning">Ingresa al menos 2 caracteres para buscar.</div>
    <?php elseif ($q !== '' && $totalResults === 0): ?>
    <div class="empty-state search-empty-state">
        <div class="empty-state-icon">🔎</div>
        <h3 class="empty-state-title">Sin resultados para "<?= htmlspecialchars($q) ?>"</h3>
        <p>Intenta con otro término de búsqueda.</p>
    </div>
    <?php elseif ($q !== ''): ?>

    <!-- Summary -->
    <div class="search-summary">
        <strong class="search-summary-count"><?= $totalResults ?></strong> resultado<?= $totalResults !== 1 ? 's' : '' ?> para "<strong><?= htmlspecialchars($q) ?></strong>"
    </div>

    <!-- Players -->
    <?php if (!empty($results['players'])): ?>
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">
                <svg class="icon-svg sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Jugadores (<?= count($results['players']) ?>)
            </h3>
        </div>
        <div class="search-result-list">
            <?php foreach ($results['players'] as $p): ?>
            <a href="<?= url('user/view/' . $p['id']) ?>" class="search-result-link">
                <?php if ($p['avatar']): ?>
                <img src="<?= UPLOAD_URL . $p['avatar'] ?>" class="search-avatar-round" alt="Avatar de <?= htmlspecialchars($p['username']) ?>">
                <?php else: ?>
                <div class="user-avatar-placeholder search-avatar-placeholder"><?= strtoupper(substr($p['username'], 0, 1)) ?></div>
                <?php endif; ?>
                <div class="search-result-text">
                    <div class="search-result-title"><?= htmlspecialchars($p['username']) ?>
                    <?php if ($p['role'] === 'admin'): ?><span class="search-role-badge admin">Admin</span><?php elseif ($p['role'] === 'designer'): ?><span class="badge badge-yellow search-role-badge designer">Designer</span><?php endif; ?>
                    </div>
                    <?php if ($p['ml_nickname'] && $p['ml_nickname'] !== $p['username']): ?>
                    <div class="search-result-subtext">IGN: <?= htmlspecialchars($p['ml_nickname']) ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($p['team_name']): ?>
                <span class="badge badge-purple"><?= htmlspecialchars($p['team_tag'] ?? $p['team_name']) ?></span>
                <?php else: ?>
                <span class="search-freelance">Agente Libre</span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Teams -->
    <?php if (!empty($results['teams'])): ?>
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">
                <svg class="icon-svg sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                Equipos (<?= count($results['teams']) ?>)
            </h3>
        </div>
        <div class="search-result-list">
            <?php foreach ($results['teams'] as $t): ?>
            <a href="<?= url('teams/view/' . $t['id']) ?>" class="search-result-link">
                <?php if ($t['logo']): ?>
                <img src="<?= UPLOAD_URL . $t['logo'] ?>" class="search-team-logo" alt="Logo de <?= htmlspecialchars($t['name']) ?>">
                <?php else: ?>
                <div class="search-team-fallback"><?= strtoupper(substr($t['name'], 0, 2)) ?></div>
                <?php endif; ?>
                <div class="search-result-text">
                    <div class="search-result-title">
                        <?= htmlspecialchars($t['name']) ?>
                        <?php if ($t['tag']): ?><span class="badge badge-purple search-team-tag"><?= $t['tag'] ?></span><?php endif; ?>
                    </div>
                    <div class="search-result-subtext"><?= $t['member_count'] ?> miembro<?= $t['member_count'] !== 1 ? 's' : '' ?> · Cap: <?= htmlspecialchars($t['captain_name'] ?? '?') ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- News -->
    <?php if (!empty($results['news'])): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <svg class="icon-svg sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 5v14H5V5"/><path d="M3 7h2v12H3z"/><path d="M7 9h6M7 13h8M7 17h4"/></svg>
                Noticias (<?= count($results['news']) ?>)
            </h3>
        </div>
        <div class="search-result-list">
            <?php foreach ($results['news'] as $n): ?>
            <a href="<?= url('news/view/' . $n['slug']) ?>" class="search-result-link">
                <?php if ($n['image']): ?>
                <img src="<?= asset('uploads/' . $n['image']) ?>" class="search-news-image" alt="<?= htmlspecialchars($n['title']) ?>">
                <?php else: ?>
                <div class="search-news-placeholder"></div>
                <?php endif; ?>
                <div class="search-result-text">
                    <div class="search-news-title"><?= htmlspecialchars($n['title']) ?></div>
                    <div class="search-news-meta">
                        <?php if ($n['category_name']): ?><span class="badge badge-purple search-news-badge"><?= htmlspecialchars($n['category_name']) ?></span> · <?php endif; ?>
                        <?= timeAgo($n['created_at']) ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Initial state — no search yet -->
    <div class="search-quick-grid">
        <a href="<?= url('teams') ?>" class="card search-quick-link">
            <div class="stat-icon purple"><svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>
            <div><div class="search-quick-title">Equipos</div><div class="search-quick-subtitle">Ver todos los equipos</div></div>
        </a>
        <a href="<?= url('tournaments') ?>" class="card search-quick-link">
            <div class="stat-icon orange"><svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4a2 2 0 01-2-2V5h4M18 9h2a2 2 0 002-2V5h-4"/><path d="M6 5a6 6 0 0012 0"/><path d="M12 15v4M8 19h8"/><rect x="6" y="3" width="12" height="2" rx="1"/></svg></div>
            <div><div class="search-quick-title">Torneos</div><div class="search-quick-subtitle">Ver todos los torneos</div></div>
        </a>
        <a href="<?= url('news') ?>" class="card search-quick-link">
            <div class="stat-icon blue"><svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 5v14H5V5"/><path d="M3 7h2v12H3z"/><path d="M7 9h6M7 13h8M7 17h4"/></svg></div>
            <div><div class="search-quick-title">Noticias</div><div class="search-quick-subtitle">Últimas noticias</div></div>
        </a>
        <a href="<?= url('rankings') ?>" class="card search-quick-link">
            <div class="stat-icon green"><svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg></div>
            <div><div class="search-quick-title">Rankings</div><div class="search-quick-subtitle">Ver clasificación</div></div>
        </a>
    </div>
    <?php endif; ?>

</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
