<?php
/**
 * Liga WOC — Dashboard (Enhanced)
 */
if (!isLoggedIn()) redirect('login');
if (isAdmin()) redirect('admin');
$pageTitle = 'Dashboard';
$page = 'dashboard';

require_once __DIR__ . '/../controllers/ShopController.php';

$db = Database::getInstance();
$userId = currentUserId();

$shopCtrl = new ShopController();
$equippedItems = $shopCtrl->getEquippedItems($userId);
$equippedMarco = null;
$equippedPortada = null;
foreach ($equippedItems as $eq) {
    if ($eq['type'] === 'marco') {
        $equippedMarco = $eq;
    } elseif ($eq['type'] === 'portada') {
        $equippedPortada = $eq;
    }

    if ($equippedMarco && $equippedPortada) {
        break;
    }
}

// Get user data
$user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);

$dashboardCoverUrl = url('assets/img/pattern.png');
if (!empty($user['cover'])) {
    $dashboardCoverUrl = url('assets/covers/' . $user['cover']);
}
if (!empty($equippedPortada) && !empty($equippedPortada['image'])) {
    $dashboardCoverUrl = url('assets/shop/' . $equippedPortada['image']);
}
$dashboardBannerStyle = "background-image: linear-gradient(90deg, rgba(12, 16, 24, 0.92), rgba(12, 16, 24, 0.72)), url('" . htmlspecialchars($dashboardCoverUrl, ENT_QUOTES) . "'); background-size: cover; background-position: center; background-repeat: no-repeat;";

// Get user's team
$teamMembership = $db->fetch("SELECT tm.*, t.name as team_name, t.tag as team_tag, t.logo, t.id as tid FROM team_members tm JOIN teams t ON tm.team_id = t.id WHERE tm.user_id = ? AND t.is_active = 1", [$userId]);
$myTeamId = $teamMembership ? $teamMembership['tid'] : 0;

// Active season
$activeSeason = $db->fetch("SELECT id, name FROM seasons WHERE is_active = 1 LIMIT 1");

// Dynamic player stats from match_player_stats
$personalKDA = $db->fetch(
    "SELECT COUNT(DISTINCT mps.match_id) as matches_played,
            SUM(mps.kills) as total_kills, SUM(mps.deaths) as total_deaths, SUM(mps.assists) as total_assists,
            (SELECT COUNT(*) FROM tournament_matches m WHERE m.status='completed' AND m.winner_id = ? AND (m.team1_id = ? OR m.team2_id = ?)) as wins
     FROM match_player_stats mps WHERE mps.user_id = ?",
    [$myTeamId, $myTeamId, $myTeamId, $userId]
);
$kda = ($personalKDA && $personalKDA['total_deaths'] > 0) 
    ? round(($personalKDA['total_kills'] + $personalKDA['total_assists']) / $personalKDA['total_deaths'], 2) 
    : ($personalKDA ? ($personalKDA['total_kills'] + $personalKDA['total_assists']) : 0);
$matchesPlayed = $personalKDA['matches_played'] ?? 0;
$wins = $personalKDA['wins'] ?? 0;
$losses = $matchesPlayed - $wins;
$winrate = $matchesPlayed > 0 ? round($wins / $matchesPlayed * 100) : 0;

// Ranking position
$rankPosition = null;
if ($myTeamId) {
    $allTeams = $db->fetchAll(
        "SELECT t.id,
                SUM(CASE WHEN m.winner_id = t.id THEN 3 ELSE 0 END) as points
         FROM teams t
         LEFT JOIN tournament_matches m ON (m.team1_id = t.id OR m.team2_id = t.id) AND m.status = 'completed'
         LEFT JOIN tournaments tr ON m.tournament_id = tr.id AND tr.is_main_tournament = 1
         WHERE t.is_active = 1
         GROUP BY t.id
         ORDER BY points DESC"
    );
    foreach ($allTeams as $i => $rt) {
        if ($rt['id'] == $myTeamId) { $rankPosition = $i + 1; break; }
    }
}

// Top 3 most used heroes
$topHeroes = $db->fetchAll(
    "SELECT hero_used, COUNT(*) as picks, SUM(kills) as k, SUM(deaths) as d, SUM(assists) as a
     FROM match_player_stats WHERE user_id = ? AND hero_used IS NOT NULL AND hero_used != ''
     GROUP BY hero_used ORDER BY picks DESC LIMIT 3",
    [$userId]
);

// Upcoming matches for my team
$upcomingMatches = [];
if ($myTeamId) {
    $upcomingMatches = $db->fetchAll(
        "SELECT m.*, t1.name as t1_name, t1.logo as t1_logo, t2.name as t2_name, t2.logo as t2_logo, tr.name as tournament_name
         FROM tournament_matches m
         LEFT JOIN teams t1 ON m.team1_id = t1.id
         LEFT JOIN teams t2 ON m.team2_id = t2.id
         LEFT JOIN tournaments tr ON m.tournament_id = tr.id
         WHERE (m.team1_id = ? OR m.team2_id = ?) AND m.status IN ('scheduled','pending')
         ORDER BY CASE WHEN m.scheduled_at IS NOT NULL THEN 0 ELSE 1 END, m.scheduled_at ASC
         LIMIT 5",
        [$myTeamId, $myTeamId]
    );
}

// Active tournaments
$activeTournaments = $db->fetchAll("SELECT id, name, format, status, start_date, max_teams, COALESCE((SELECT COUNT(*) FROM tournament_teams WHERE tournament_id = tournaments.id AND status = 'registered'), 0) as registered_teams FROM tournaments WHERE status IN ('registration','in_progress') ORDER BY created_at DESC LIMIT 4");

// Latest news
$latestNews = $db->fetchAll("SELECT n.id, n.title, n.slug, n.excerpt, n.image, n.created_at, n.views, nc.name as category_name FROM news n LEFT JOIN news_categories nc ON n.category_id = nc.id WHERE n.is_published = 1 AND n.publish_date <= NOW() ORDER BY n.is_featured DESC, n.created_at DESC LIMIT 3");

// Unread notifications
$unreadCount = $db->fetch("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0", [$userId])['c'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

// Helper
function findHeroImg($heroName) {
    if (!$heroName) return '';
    $hn = strtolower(trim($heroName));
    foreach (ML_ROLE_FOLDERS as $r => $fn) {
        $dir = __DIR__ . "/../assets/heroes_img/{$fn}/";
        if (is_dir($dir)) {
            foreach (array_diff(scandir($dir), ['..', '.']) as $f) {
                if (strtolower(pathinfo($f, PATHINFO_FILENAME)) == $hn) return url("assets/heroes_img/{$fn}/{$f}");
            }
        }
    }
    return '';
}
?>
<div class="app-wrapper">
<div class="main-content dashboard-main">

    <!-- Player Banner -->
    <div class="player-banner mb-4" style="<?= $dashboardBannerStyle ?>">
        <div class="player-banner-bg"></div>
        <div class="player-banner-content">
            <div class="player-avatar-large dashboard-avatar-shell">
                <?php 
                $avatarFrameClass = 'dashboard-avatar-frame';
                $avatarFrameImage = null;
                if (!empty($equippedMarco) && !empty($equippedMarco['image'])) {
                    $avatarFrameClass .= ' dashboard-avatar-frame--marco';
                    $avatarFrameImage = url('assets/shop/' . $equippedMarco['image']);
                }
                ?>
                <div class="<?= $avatarFrameClass ?>">
                    <?php if ($avatarFrameImage): ?>
                        <img src="<?= htmlspecialchars($avatarFrameImage, ENT_QUOTES) ?>" class="dashboard-avatar-marco-image" alt="">
                    <?php endif; ?>
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= UPLOAD_URL . $user['avatar'] ?>" class="dashboard-avatar-image" alt="<?= htmlspecialchars($user['username']) ?>">
                    <?php else: ?>
                        <div class="dashboard-avatar-fallback"><?= strtoupper(substr($user['username'] ?? 'U', 0, 1)) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="player-info">
                <h1><?= htmlspecialchars($user['username']) ?></h1>
                <div class="player-team">
                    <?php if ($teamMembership): ?>
                        <span class="team-tag">[<?= htmlspecialchars($teamMembership['team_tag']) ?>]</span> <?= htmlspecialchars($teamMembership['team_name']) ?>
                        <?php if ($rankPosition): ?>
                        <span class="dash-rank-badge">#<?= $rankPosition ?> Ranking</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="dashboard-free-agent">Agente Libre</span>
                        <a href="<?= url('teams') ?>" class="dashboard-recruit-link">Reclutamiento &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="player-stats-fluid">
                <div class="stat-item">
                    <span class="stat-val"><?= $wins ?>/<?= $losses ?></span>
                    <span class="stat-lbl">V/D</span>
                </div>
                <div class="stat-item">
                    <span class="stat-val stat-val--accent"><?= $kda ?></span>
                    <span class="stat-lbl">KDA</span>
                </div>
                <div class="stat-item">
                    <span class="stat-val <?= $winrate >= 50 ? 'stat-val--positive' : 'stat-val--negative' ?>"><?= $winrate ?>%</span>
                    <span class="stat-lbl">Win Rate</span>
                </div>
                <div class="stat-item">
                    <span class="stat-val"><?= $matchesPlayed ?></span>
                    <span class="stat-lbl">Partidas</span>
                </div>
            </div>
            <?php if ($unreadCount > 0): ?>
            <a href="<?= url('notifications') ?>" class="notif-alert-btn">
                <span class="pulse"></span> <?= $unreadCount ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Heroes -->
    <?php if (!empty($topHeroes)): ?>
    <div class="dash-heroes-row mb-3">
        <h3 class="dashboard-heroes-title">Héroes más usados:</h3>
        <?php foreach ($topHeroes as $th): 
            $heroImg = findHeroImg($th['hero_used']);
            $hKda = $th['d'] > 0 ? round(($th['k'] + $th['a']) / $th['d'], 1) : ($th['k'] + $th['a']);
        ?>
        <div class="dash-hero-chip">
            <?php if ($heroImg): ?>
            <img src="<?= $heroImg ?>" alt="">
            <?php endif; ?>
            <div class="dashboard-hero-copy">
                <div class="dashboard-hero-name"><?= htmlspecialchars($th['hero_used']) ?></div>
                <div class="dashboard-hero-meta"><?= $th['picks'] ?> pick<?= $th['picks']>1?'s':'' ?> · <?= $hKda ?> KDA</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Upcoming Matches -->
    <?php if (!empty($upcomingMatches)): ?>
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">📅 Próximas Partidas</h3>
        </div>
        <?php foreach ($upcomingMatches as $um): 
            $opponent = ($um['team1_id'] == $myTeamId) ? $um['t2_name'] : $um['t1_name'];
            $opponentLogo = ($um['team1_id'] == $myTeamId) ? $um['t2_logo'] : $um['t1_logo'];
        ?>
        <div class="dash-upcoming-match">
            <div class="dash-um-left">
                <?php if ($opponentLogo): ?>
                <img src="<?= UPLOAD_URL . $opponentLogo ?>" class="dash-um-logo" alt="">
                <?php else: ?>
                <div class="dash-um-logo-ph"><?= strtoupper(substr($opponent ?? '?', 0, 2)) ?></div>
                <?php endif; ?>
                <div class="dashboard-um-copy">
                    <div class="dashboard-um-name">vs <?= htmlspecialchars($opponent ?? 'TBD') ?></div>
                    <div class="dashboard-um-meta"><?= htmlspecialchars($um['tournament_name']) ?> · R<?= $um['round'] ?></div>
                </div>
            </div>
            <div class="dash-um-right">
                <?php if ($um['scheduled_at']): ?>
                <span class="dash-um-date">📅 <?= date('d M, H:i', strtotime($um['scheduled_at'])) ?></span>
                <?php else: ?>
                <span class="dash-um-pending">⏳ Sin programar</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-2 mb-3">
        <!-- Active Tournaments -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Torneos Activos</h3>
                <a href="<?= url('tournaments') ?>" class="btn btn-sm btn-secondary">Ver todos</a>
            </div>
            <?php if (!empty($activeTournaments)): ?>
                <?php foreach ($activeTournaments as $t): ?>
                <a href="<?= url('tournaments/view/' . $t['id']) ?>" class="dashboard-tournament-link">
                    <div class="dashboard-tournament-body">
                        <div class="dashboard-tournament-name"><?= htmlspecialchars($t['name']) ?></div>
                        <div class="dashboard-tournament-meta"><?= $t['registered_teams'] ?>/<?= $t['max_teams'] ?> equipos &middot; <?= TOURNAMENT_FORMATS[$t['format']] ?? $t['format'] ?></div>
                    </div>
                    <span class="tournament-status <?= $t['status'] ?>"><?= ucfirst(str_replace('_',' ',$t['status'])) ?></span>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state dashboard-empty-state"><p class="dashboard-empty-copy">No hay torneos activos</p></div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Acciones Rapidas</h3>
            </div>
            <div class="dashboard-actions-grid">
                <a href="<?= url('teams') ?>" class="btn btn-secondary btn-sm dashboard-action-link">Equipos</a>
                <a href="<?= url('tournaments') ?>" class="btn btn-secondary btn-sm dashboard-action-link">Torneos</a>
                <a href="<?= url('rankings') ?>" class="btn btn-secondary btn-sm dashboard-action-link">Rankings</a>
                <a href="<?= url('match-history') ?>" class="btn btn-secondary btn-sm dashboard-action-link">Historial</a>
                <a href="<?= url('profile') ?>" class="btn btn-secondary btn-sm dashboard-action-link">Mi Perfil</a>
                <a href="<?= url('stream') ?>" class="btn btn-secondary btn-sm dashboard-action-link">En Vivo</a>
                <?php if (!$teamMembership && !isAdmin() && !isSuperAdmin()): ?>
                <a href="<?= url('teams/create') ?>" class="btn btn-primary btn-sm dashboard-action-link dashboard-action-link--full">Crear Equipo</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Latest News -->
    <?php if (!empty($latestNews)): ?>
    <div class="card-header dashboard-section-header">
        <h3 class="card-title dashboard-section-title">Últimas Noticias</h3>
        <a href="<?= url('news') ?>" class="btn btn-sm btn-secondary">Ver todas</a>
    </div>
    <div class="grid grid-3 dashboard-news-grid">
        <?php foreach ($latestNews as $article): ?>
        <a href="<?= url('news/view/' . $article['slug']) ?>" class="news-card dashboard-news-link">
            <?php if ($article['image']): ?>
                <div class="dashboard-news-media"><img src="<?= asset('uploads/' . $article['image']) ?>" class="news-card-img" alt=""></div>
            <?php else: ?>
                <div class="news-card-img-placeholder dashboard-news-placeholder"></div>
            <?php endif; ?>
            <div class="news-card-body dashboard-news-body">
                <?php if ($article['category_name']): ?>
                    <span class="news-card-category dashboard-news-category"><?= htmlspecialchars($article['category_name']) ?></span>
                <?php endif; ?>
                <h4 class="news-card-title dashboard-news-title"><?= htmlspecialchars($article['title']) ?></h4>
                <div class="news-card-meta dashboard-news-meta">
                    <span><?= timeAgo($article['created_at']) ?></span>
                    <span><?= $article['views'] ?> vistas</span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
