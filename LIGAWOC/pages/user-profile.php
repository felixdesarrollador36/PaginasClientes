<?php
$pageTitle = 'Perfil de Jugador';
$pageCss = 'user-profile';
$page = 'user';
$userId = $_GET['id'] ?? 0;

$db = Database::getInstance();

// Validar usuario
$user = $db->fetch("SELECT id, username, email, avatar, role, bio, ml_id, ml_server, ml_nickname, is_banned, created_at FROM users WHERE id = ?", [$userId]);
if (!$user) {
    setFlash('error', 'Usuario no encontrado.');
    redirect('home');
}

// Obtener equipo actual del usuario
$teamCtrl = new TeamController();
$myTeam = $teamCtrl->getUserTeam($userId);

// Obtener items equipados del usuario
require_once __DIR__ . '/../controllers/ShopController.php';
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
}

// Estadísticas del jugador
$stats = $db->fetch(
    "SELECT COUNT(DISTINCT match_id) as total_games,
            COALESCE(SUM(kills),0) as total_kills,
            COALESCE(SUM(deaths),0) as total_deaths,
            COALESCE(SUM(assists),0) as total_assists,
            COALESCE(AVG(mvp_score),0) as avg_score
     FROM match_player_stats 
     WHERE user_id = ?",
    [$userId]
);

$kda = ($stats['total_deaths'] > 0) ? round(($stats['total_kills'] + $stats['total_assists']) / $stats['total_deaths'], 2) : ($stats['total_kills'] + $stats['total_assists']);

// Winrate basado en tournament_matches donde su equipo equipo participó y él jugó (o simplemente wins del equipo mientras estaba)
// Forma simple: Winrate = Ganadas / Partidas jugadas del user_id en match_player_stats
$winStats = $db->fetch(
    "SELECT COUNT(*) as wins
     FROM match_player_stats mps
     JOIN tournament_matches tm ON mps.match_id = tm.id AND mps.team_id = tm.winner_id
     WHERE mps.user_id = ?",
    [$userId]
);
$wins = $winStats['wins'];
$winrate = $stats['total_games'] > 0 ? round(($wins / $stats['total_games']) * 100) : 0;

// Top 3 Héroes
$topHeroes = $db->fetchAll(
    "SELECT hero_used, COUNT(*) as games, SUM(kills) as k, SUM(deaths) as d, SUM(assists) as a, AVG(mvp_score) as avg_score
     FROM match_player_stats
     WHERE user_id = ? AND hero_used IS NOT NULL
     GROUP BY hero_used
     ORDER BY games DESC, avg_score DESC
     LIMIT 3",
    [$userId]
);

// Partidas recientes
$recentMatches = $db->fetchAll(
    "SELECT tm.*, mps.hero_used, mps.kills, mps.deaths, mps.assists, mps.mvp_score, 
            t1.name as t1_name, t1.logo as t1_logo, 
            t2.name as t2_name, t2.logo as t2_logo, 
            w.name as winner_name, tr.name as tournament_name
     FROM match_player_stats mps
     JOIN tournament_matches tm ON mps.match_id = tm.id
     LEFT JOIN teams t1 ON tm.team1_id = t1.id
     LEFT JOIN teams t2 ON tm.team2_id = t2.id
     LEFT JOIN teams w ON tm.winner_id = w.id
     LEFT JOIN tournaments tr ON tm.tournament_id = tr.id
     WHERE mps.user_id = ? AND tm.status = 'completed'
     ORDER BY tm.completed_at DESC LIMIT 5",
    [$userId]
);

// Hero image helper
function upFindHeroImg($name) {
    if (!$name) return '';
    $n = strtolower(trim($name));
    foreach (ML_ROLE_FOLDERS as $r => $fn) {
        $d = __DIR__ . "/../assets/heroes_img/{$fn}/";
        if (is_dir($d)) { foreach (array_diff(scandir($d), ['..', '.']) as $f) { if (strtolower(pathinfo($f, PATHINFO_FILENAME)) == $n) return url("assets/heroes_img/{$fn}/{$f}"); } }
    }
    return '';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content">
    <div class="page-header up-page-header">
        <h1 class="page-title up-page-title-hidden">Perfil de <?= htmlspecialchars($user['username']) ?></h1>
    </div>

    <!-- Premium Profile Hero -->
    <div class="profile-hero card mb-4 up-profile-hero">
        <?php 
        $coverUrl = url('assets/img/pattern.png');
        
        if (!empty($equippedPortada) && !empty($equippedPortada['image'])) {
            $coverUrl = url('assets/shop/' . $equippedPortada['image']);
        }
        ?>
        <div class="up-cover">
            <img src="<?= $coverUrl ?>" class="up-cover-image" alt="Portada de perfil">
            <?php if ($user['is_banned']): ?>
            <div class="up-banned-badge">
                BANEADO
            </div>
            <?php endif; ?>
            
            <div class="up-identity-row">
                <div class="up-avatar-shell">
                    <div class="up-avatar-frame <?= (!empty($equippedMarco) && !empty($equippedMarco['image'])) ? 'has-marco' : '' ?>">
                        <?php if (!empty($equippedMarco) && !empty($equippedMarco['image'])): ?>
                            <img src="<?= url('assets/shop/' . $equippedMarco['image']) ?>" class="up-marco-layer" alt="Marco equipado">
                        <?php endif; ?>
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?= UPLOAD_URL . $user['avatar'] ?>" class="up-avatar-img" alt="Avatar de <?= htmlspecialchars($user['username']) ?>">
                        <?php else: ?>
                            <div class="up-avatar-placeholder"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="up-user-meta">
                    <h2 class="up-username">
                        <?= htmlspecialchars($user['username']) ?>
                    </h2>
                    <div class="up-identity-tags">
                        <?php if ($user['role'] === 'admin'): ?>
                        <span class="up-role-badge up-role-admin">Admin</span>
                        <?php elseif ($user['role'] === 'designer'): ?>
                        <span class="up-role-badge up-role-designer">Designer</span>
                        <?php else: ?>
                        <span class="up-role-badge up-role-player">Player</span>
                        <?php endif; ?>
                        <?php if ($user['ml_nickname']): ?>
                            <span class="up-ign">IGN: <?= htmlspecialchars($user['ml_nickname']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="profile-main-column">
            <!-- Estadísticas principales -->
            <div class="card mb-4">
                <h3 class="card-title up-section-title up-section-title-sm">
                    <svg class="icon-svg up-section-icon up-section-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10M18 20V4M6 20v-4"/></svg>
                    Estadísticas Globales
                </h3>
                <div class="up-stats-grid">
                    <div class="stat-card up-stat-card">
                        <div class="up-stat-value"><?= $stats['total_games'] ?></div>
                        <div class="up-stat-label">Partidas</div>
                    </div>
                    <div class="stat-card up-stat-card">
                        <div class="up-stat-value <?= $winrate >= 50 ? 'up-stat-positive' : 'up-stat-negative' ?>"><?= $winrate ?>%</div>
                        <div class="up-stat-label">Win Rate</div>
                    </div>
                    <div class="stat-card up-stat-card">
                        <div class="up-stat-value up-stat-kda"><?= $kda ?></div>
                        <div class="up-stat-label">KDA</div>
                    </div>
                    <div class="stat-card up-stat-card">
                        <div class="up-stat-kda-line">
                            <?= $stats['total_kills'] ?>/<span class="up-stat-deaths"><?= $stats['total_deaths'] ?></span>/<?= $stats['total_assists'] ?>
                        </div>
                        <div class="up-stat-label">K/D/A</div>
                    </div>
                </div>
            </div>

            <!-- Top Héroes -->
            <?php if (!empty($topHeroes)): ?>
            <div class="card mb-4">
                <h3 class="card-title up-section-title">
                    <svg class="icon-svg up-section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    Héroes Más Usados
                </h3>
                <div class="up-hero-list">
                    <?php foreach ($topHeroes as $index => $hero): 
                        $heroImg = upFindHeroImg($hero['hero_used']);
                        $heroKda = $hero['d'] > 0 ? round(($hero['k'] + $hero['a']) / $hero['d'], 1) : ($hero['k'] + $hero['a']);
                    ?>
                    <div class="up-hero-item">
                        <?php if ($heroImg): ?>
                            <img src="<?= $heroImg ?>" class="up-hero-img <?= $index === 0 ? 'is-top' : '' ?>" alt="<?= htmlspecialchars($hero['hero_used']) ?>">
                        <?php else: ?>
                            <div class="up-hero-fallback"><?= strtoupper(substr($hero['hero_used'], 0, 1)) ?></div>
                        <?php endif; ?>
                        
                        <div class="up-hero-info">
                            <h4 class="up-hero-name"><?= htmlspecialchars($hero['hero_used']) ?></h4>
                            <div class="up-hero-meta">
                                <?= $hero['games'] ?> Partidas jugadas • <span class="up-hero-grade">Nota Promedio: <?= round($hero['avg_score'], 1) ?></span>
                            </div>
                        </div>
                        
                        <div class="up-hero-kda-box">
                            <div class="up-hero-kda-value"><?= $heroKda ?></div>
                            <div class="up-hero-kda-label">KDA</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="profile-sidebar">
            <!-- Equipo Actual -->
            <?php if ($myTeam): ?>
            <div class="card mb-4 up-team-card">
                <h3 class="card-title up-section-title up-team-title">
                    <svg class="icon-svg up-team-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 6 6 1-4 4 1 6-6-3-6 3 1-6-4-4 6-1 3-6z"/></svg>
                    EQUIPO ACTUAL
                </h3>
                
                <div class="up-team-head">
                    <?php if ($myTeam['logo']): ?>
                        <img src="<?= UPLOAD_URL . $myTeam['logo'] ?>" class="up-team-logo" alt="Logo de <?= htmlspecialchars($myTeam['team_name']) ?>">
                    <?php else: ?>
                        <div class="team-logo-placeholder up-team-logo-placeholder"><?= strtoupper(substr($myTeam['team_name'], 0, 2)) ?></div>
                    <?php endif; ?>
                    <div>
                        <div class="up-team-name"><?= htmlspecialchars($myTeam['team_name']) ?></div>
                        <div class="up-team-meta">
                            <span class="badge badge-purple up-mini-badge"><?= ML_ROLES[$myTeam['role']] ?? $myTeam['role'] ?></span>
                            <?= $myTeam['is_captain'] ? '<span class="badge badge-yellow up-mini-badge up-cap-badge">👑 Cap</span>' : '' ?>
                        </div>
                    </div>
                </div>
                
                <a href="<?= url('teams/view/' . $myTeam['team_id']) ?>" class="btn btn-secondary btn-block up-team-btn">
                    Ver Perfil del Equipo
                </a>
            </div>
            <?php else: ?>
            <div class="card mb-4">
                <div class="empty-state up-empty-state">
                    <div class="empty-state-icon up-empty-state-icon">
                        <svg class="icon-svg up-empty-state-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    </div>
                    <h3 class="empty-state-title up-empty-state-title">Agente Libre</h3>
                    <p class="up-empty-state-text">Actualmente no pertenece a ningún equipo.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Partidas Recientes -->
            <?php if (!empty($recentMatches)): ?>
            <div class="card">
                <h3 class="card-title up-section-title">📜 Historial Reciente</h3>
                <div class="up-recent-list">
                    <?php foreach ($recentMatches as $rm): 
                        $won = ($rm['winner_id'] == ($rm['team1_id'] == $myTeam['team_id'] ?? 0 ? $rm['team1_id'] : $rm['team2_id']));
                    ?>
                    <div class="up-recent-item <?= $won ? 'won' : 'lost' ?>">
                        <div class="up-recent-top">
                            <span class="up-result-badge <?= $won ? 'won' : 'lost' ?>"><?= $won ? 'VICTORIA' : 'DERROTA' ?></span>
                            <span class="up-time-ago"><?= timeAgo($rm['completed_at']) ?></span>
                        </div>
                        <div class="up-recent-main">
                            <div class="up-hero-mini">
                                <?php $hImg = upFindHeroImg($rm['hero_used']); if ($hImg): ?>
                                    <img src="<?= $hImg ?>" class="up-hero-mini-img" title="<?= htmlspecialchars($rm['hero_used']) ?>" alt="<?= htmlspecialchars($rm['hero_used']) ?>">
                                <?php else: ?>
                                    <div class="up-hero-mini-fallback"><?= strtoupper(substr($rm['hero_used'] ?? '?', 0, 1)) ?></div>
                                <?php endif; ?>
                                <div>
                                    <div class="up-hero-mini-name"><?= htmlspecialchars($rm['hero_used'] ?? 'Desconocido') ?></div>
                                    <div class="up-hero-mini-kda"><?= $rm['kills'] ?> / <?= $rm['deaths'] ?> / <?= $rm['assists'] ?></div>
                                </div>
                            </div>
                            <div class="up-score-box">
                                <div class="up-score-label">Score</div>
                                <div class="up-score-value"><?= $rm['mvp_score'] ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="up-history-actions">
                    <a href="<?= url('match-history?search=' . urlencode($user['username'])) ?>" class="btn btn-sm up-history-btn">Ver Historial Completo 👀</a>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="up-member-since">
                Miembro desde el <?= date('d de F, Y', strtotime($user['created_at'])) ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
