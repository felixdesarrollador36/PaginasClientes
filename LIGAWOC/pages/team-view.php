<?php
$page = 'teams';
$pageCss = 'team-view';
$teamId = $_GET['id'] ?? 0;
$teamCtrl = new TeamController();
$team = $teamCtrl->getTeam($teamId);
if (!$team) { setFlash('error', 'Equipo no encontrado.'); redirect('teams'); }
$members = $teamCtrl->getTeamMembers($teamId);
$effectiveTeamMax = max((int)($team['max_members'] ?? 0), 20);
$pageTitle = $team['name'];
$myTeam = $teamCtrl->getUserTeam(currentUserId());
$isMyTeam = ($myTeam && $myTeam['team_id'] == $teamId);
$isCaptain = ($team['captain_id'] == currentUserId());

$db = Database::getInstance();

// Team match stats
$teamStats = $db->fetch(
    "SELECT COUNT(DISTINCT m.id) as matches_played,
            SUM(CASE WHEN m.winner_id = ? THEN 1 ELSE 0 END) as wins,
            SUM(CASE WHEN m.loser_id = ? THEN 1 ELSE 0 END) as losses
     FROM tournament_matches m
     WHERE (m.team1_id = ? OR m.team2_id = ?) AND m.status = 'completed'",
    [$teamId, $teamId, $teamId, $teamId]
);
$dynWins = intval($teamStats['wins'] ?? 0);
$dynLosses = intval($teamStats['losses'] ?? 0);
$dynMatches = intval($teamStats['matches_played'] ?? 0);
$dynPoints = $dynWins * 3;
$dynWinrate = $dynMatches > 0 ? round(($dynWins / $dynMatches) * 100) : 0;

// Tournament participation history
$tournamentHistory = $db->fetchAll(
    "SELECT DISTINCT tr.id, tr.name, tr.format, tr.status, tr.start_date,
            (SELECT COUNT(*) FROM tournament_matches m2 WHERE m2.tournament_id = tr.id AND (m2.team1_id = ? OR m2.team2_id = ?) AND m2.status='completed') as matches_in_tournament,
            (SELECT COUNT(*) FROM tournament_matches m3 WHERE m3.tournament_id = tr.id AND m3.winner_id = ?) as wins_in_tournament
     FROM tournament_teams tt
     JOIN tournaments tr ON tt.tournament_id = tr.id
     WHERE tt.team_id = ? AND tt.status = 'registered'
     ORDER BY tr.start_date DESC, tr.created_at DESC",
    [$teamId, $teamId, $teamId, $teamId]
);

// Member individual stats (from match_player_stats)
$memberStats = [];
foreach ($members as $mem) {
    $ms = $db->fetch(
        "SELECT COUNT(DISTINCT mps.match_id) as games,
                COALESCE(SUM(mps.kills),0) as k, COALESCE(SUM(mps.deaths),0) as d, COALESCE(SUM(mps.assists),0) as a,
                (SELECT hero_used FROM match_player_stats WHERE user_id = ? AND team_id = ? AND hero_used IS NOT NULL GROUP BY hero_used ORDER BY COUNT(*) DESC LIMIT 1) as top_hero
         FROM match_player_stats mps WHERE mps.user_id = ? AND mps.team_id = ?",
        [$mem['user_id'], $teamId, $mem['user_id'], $teamId]
    );
    $memberStats[$mem['user_id']] = $ms;
}

// Recent matches
$recentMatches = $db->fetchAll(
    "SELECT m.*, t1.name as t1_name, t1.logo as t1_logo, t2.name as t2_name, t2.logo as t2_logo, w.name as winner_name, tr.name as tournament_name
     FROM tournament_matches m
     LEFT JOIN teams t1 ON m.team1_id = t1.id
     LEFT JOIN teams t2 ON m.team2_id = t2.id
     LEFT JOIN teams w ON m.winner_id = w.id
     LEFT JOIN tournaments tr ON m.tournament_id = tr.id
     WHERE (m.team1_id = ? OR m.team2_id = ?) AND m.status = 'completed'
     ORDER BY m.completed_at DESC LIMIT 5",
    [$teamId, $teamId]
);

// Hero image helper
function tvFindHeroImg($name) {
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
<div class="main-content team-view-main">
    <a href="<?= url('teams') ?>" class="team-view-back-link">← Equipos</a>

    <!-- Team Header -->
    <div class="card mb-3 team-view-hero">
        <?php if ($team['logo']): ?>
            <img src="<?= UPLOAD_URL . $team['logo'] ?>" class="team-view-logo" alt="<?= htmlspecialchars($team['name']) ?>">
            <?php if (isAdmin() || isModerator()): ?>
                <div style="margin-top: 10px;">
                    <a href="<?= UPLOAD_URL . $team['logo'] ?>" download="<?= htmlspecialchars($team['name']) ?>_logo.jpg" class="btn btn-sm btn-secondary">Descargar logo</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="team-logo-placeholder team-view-logo-placeholder"><?= strtoupper(substr($team['name'], 0, 2)) ?></div>
        <?php endif; ?>
        <div class="team-view-summary">
            <h1 class="page-title team-view-title">
                <?= htmlspecialchars($team['name']) ?>
                <?php if ($team['tag']): ?>
                <span class="team-view-tag">[<?= htmlspecialchars($team['tag']) ?>]</span>
                <?php endif; ?>
            </h1>
            <p class="team-view-meta-line">Capitán: <strong class="team-view-highlight"><?= $team['captain_name'] ?></strong> • <?= $team['region'] ?: 'Sin región' ?></p>
            <?php if ($team['description']): ?>
            <p class="team-view-description"><?= htmlspecialchars($team['description']) ?></p>
            <?php endif; ?>
        </div>
        <div class="team-view-hero-stats">
            <div class="team-view-hero-stat">
                <div class="team-view-hero-value team-view-hero-value--success"><?= $dynWins ?></div>
                <div class="team-view-hero-label">Victorias</div>
            </div>
            <div class="team-view-hero-stat">
                <div class="team-view-hero-value team-view-hero-value--danger"><?= $dynLosses ?></div>
                <div class="team-view-hero-label">Derrotas</div>
            </div>
            <div class="team-view-hero-stat">
                <div class="team-view-hero-value <?= $dynWinrate >= 50 ? 'team-view-hero-value--success' : 'team-view-hero-value--danger' ?>"><?= $dynWinrate ?>%</div>
                <div class="team-view-hero-label">Winrate</div>
            </div>
            <div class="team-view-hero-stat">
                <div class="team-view-hero-value team-view-hero-value--accent"><?= $dynPoints ?></div>
                <div class="team-view-hero-label">Puntos</div>
            </div>
        </div>
    </div>

    <div class="grid grid-2">
        <!-- Roster with KDA -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">👥 Roster (<?= count($members) ?>/<?= $effectiveTeamMax ?>)</h2>
                <?php if (!$myTeam && count($members) < $effectiveTeamMax && !isAdmin() && !isSuperAdmin()): ?>
                    <a href="<?= url('teams/join/' . $teamId) ?>" class="btn btn-primary btn-sm">Unirse</a>
                <?php elseif ($isCaptain): ?>
                    <a href="<?= url('teams/manage/' . $teamId) ?>" class="btn btn-secondary btn-sm">⚙️ Gestionar</a>
                <?php elseif ($isMyTeam): ?>
                    <form method="POST" action="<?= url('teams/leave/' . $teamId) ?>" style="display:inline;">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro que quieres salir del equipo?');">Salir del equipo</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php foreach ($members as $member): 
                $ms = $memberStats[$member['user_id']] ?? ['games' => 0, 'k' => 0, 'd' => 0, 'a' => 0, 'top_hero' => null];
                $mKda = $ms['d'] > 0 ? round(($ms['k'] + $ms['a']) / $ms['d'], 1) : ($ms['k'] + $ms['a']);
                $topHeroImg = tvFindHeroImg($ms['top_hero'] ?? '');
            ?>
            <div class="team-view-member">
                <?php if ($member['avatar']): ?>
                    <img src="<?= UPLOAD_URL . $member['avatar'] ?>" class="team-view-member-avatar" alt="<?= htmlspecialchars($member['username']) ?>">
                <?php else: ?>
                    <div class="user-avatar-placeholder team-view-member-avatar team-view-member-avatar-placeholder"><?= strtoupper(substr($member['username'], 0, 1)) ?></div>
                <?php endif; ?>
                <div class="team-view-member-copy">
                    <div class="team-view-member-name">
                        <a href="<?= url('user/view/' . $member['user_id']) ?>" class="team-view-member-link"><?= htmlspecialchars(!empty($member['ml_nickname']) ? $member['ml_nickname'] : $member['username']) ?></a>
                        <?php if ($member['is_captain']): ?>
                        <span class="badge badge-yellow team-view-captain-badge">👑</span>
                        <?php endif; ?>
                    </div>
                    <div class="team-view-member-meta">
                        <span class="badge badge-purple team-view-role-badge"><?= ML_ROLES[$member['role']] ?? $member['role'] ?></span>
                        <?= ML_LANES[$member['lane_1']] ?? '' ?><?= $member['lane_2'] ? ' / ' . (ML_LANES[$member['lane_2']] ?? '') : '' ?>
                    </div>
                </div>
                <!-- Member KDA -->
                <div class="team-view-member-kda-row">
                    <?php if ($topHeroImg): ?>
                    <img src="<?= $topHeroImg ?>" class="team-view-top-hero" title="<?= htmlspecialchars($ms['top_hero'] ?? '') ?>" alt="<?= htmlspecialchars($ms['top_hero'] ?? '') ?>">
                    <?php endif; ?>
                    <div class="team-view-member-kda">
                        <div class="team-view-member-kda-value"><?= $mKda ?> <span class="team-view-member-kda-label">KDA</span></div>
                        <div class="team-view-member-games"><?= $ms['games'] ?> partidas</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="team-view-side">
            <!-- Team Statistics -->
            <div class="card mb-2 team-view-stats-card">
                <h3 class="team-view-side-title">⚔️ Estadísticas</h3>
                <div class="team-view-stats-grid">
                    <div class="stat-card team-view-stat-card">
                        <div class="stat-value team-view-stat-value"><?= $dynMatches ?></div>
                        <div class="stat-label">Partidas</div>
                    </div>
                    <div class="stat-card team-view-stat-card">
                        <div class="stat-value team-view-stat-value <?= $dynWinrate >= 50 ? 'team-view-stat-value--success' : 'team-view-stat-value--danger' ?>"><?= $dynWinrate ?>%</div>
                        <div class="stat-label">Winrate</div>
                    </div>
                    <div class="stat-card team-view-stat-card">
                        <div class="stat-value team-view-stat-value"><?= count($members) ?></div>
                        <div class="stat-label">Miembros</div>
                    </div>
                </div>
            </div>

            <!-- Tournament History -->
            <?php if (!empty($tournamentHistory)): ?>
            <div class="card mb-2">
                <div class="card-header"><h3 class="card-title team-view-card-title">🏆 Torneos</h3></div>
                <?php foreach ($tournamentHistory as $th): ?>
                <a href="<?= url('tournaments/view/' . $th['id']) ?>" class="team-view-tournament-link">
                    <div class="team-view-tournament-copy">
                        <div class="team-view-tournament-name"><?= htmlspecialchars($th['name']) ?></div>
                        <div class="team-view-tournament-meta"><?= $th['wins_in_tournament'] ?>V / <?= $th['matches_in_tournament'] - $th['wins_in_tournament'] ?>D</div>
                    </div>
                    <span class="tournament-status team-view-tournament-status <?= $th['status'] ?>"><?= ucfirst(str_replace('_',' ',$th['status'])) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Recent Matches -->
            <?php if (!empty($recentMatches)): ?>
            <div class="card">
                <div class="card-header"><h3 class="card-title team-view-card-title">📜 Últimas Partidas</h3></div>
                <?php foreach ($recentMatches as $rm): 
                    $won = ($rm['winner_id'] == $teamId);
                    $opponent = ($rm['team1_id'] == $teamId) ? $rm['t2_name'] : $rm['t1_name'];
                ?>
                <div class="team-view-match-row">
                    <div class="team-view-match-main">
                        <span class="badge team-view-result-badge <?= $won ? 'badge-green' : 'badge-red' ?>"><?= $won ? 'W' : 'L' ?></span>
                        <span class="team-view-match-opponent">vs <?= htmlspecialchars($opponent ?? 'TBD') ?></span>
                        <span class="team-view-match-score"><?= $rm['team1_score'] ?>-<?= $rm['team2_score'] ?></span>
                    </div>
                    <span class="team-view-match-tournament"><?= $rm['tournament_name'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($isMyTeam || $isCaptain): ?>
            <div class="card mt-2 team-view-cta">
                <div class="team-view-cta-icon">🏆</div>
                <h3 class="team-view-cta-title">¿Listo para el torneo?</h3>
                <a href="<?= url('tournaments') ?>" class="btn btn-primary btn-sm">Ver Torneos Disponibles</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
