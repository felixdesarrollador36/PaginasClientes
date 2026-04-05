<?php
/**
 * Liga WOC — Match History (Public, detailed)
 */
$pageTitle = 'Historial de Partidas';
$page = 'match-history';
$pageCss = 'match-history';
$db = Database::getInstance();
$userId = currentUserId();
$teamCtrl = new TeamController();
$myTeam = $teamCtrl->getUserTeam($userId);
$myTeamId = $myTeam ? $myTeam['team_id'] : 0;

// Filters
$filterTournament = isset($_GET['tournament']) && $_GET['tournament'] !== '' ? intval($_GET['tournament']) : null;
$filterView = $_GET['view'] ?? ($myTeam ? 'mine' : 'all');

// Available tournaments for filter
$tournaments = $db->fetchAll("SELECT id, name FROM tournaments ORDER BY created_at DESC");

// Handle Comment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) {
        redirect('login');
    }
    verifyCsrf();
    
    $action = $_POST['action'] ?? 'add_comment';
    
    if ($action === 'delete_comment' && (isAdmin() || isSuperAdmin())) {
        $commentId = intval($_POST['comment_id'] ?? 0);
        $db->delete("DELETE FROM match_comments WHERE id = ?", [$commentId]);
        setFlash('success', 'Comentario eliminado.');
        redirect('match-history');
    }
    
    if ($action === 'add_comment') {
        $matchId = intval($_POST['match_id'] ?? 0);
        $comment = sanitize($_POST['comment'] ?? '');

        if ($matchId && !empty($comment)) {
            try {
                $db->insert(
                    "INSERT INTO match_comments (match_id, user_id, comment) VALUES (?, ?, ?)",
                    [$matchId, currentUserId(), $comment]
                );
                setFlash('success', 'Comentario publicado.');
            } catch (Exception $e) {
                setFlash('error', 'Error al publicar comentario: ' . $e->getMessage());
            }
        } else {
            setFlash('error', 'El comentario no puede estar vacío.');
        }
        
        $redirectParams = http_build_query(array_filter([
            'view'       => $filterView,
            'tournament' => $filterTournament ?: null,
        ]));
        redirect('match-history' . ($redirectParams ? '?'.$redirectParams : ''));
    }
}

// Build query
$where = "WHERE m.status = 'completed'";
$params = [];

if ($filterView === 'mine' && $myTeamId) {
    $where .= " AND (m.team1_id = ? OR m.team2_id = ?)";
    $params[] = $myTeamId;
    $params[] = $myTeamId;
}

if ($filterTournament) {
    $where .= " AND m.tournament_id = ?";
    $params[] = $filterTournament;
}

$matches = $db->fetchAll(
    "SELECT m.*, 
            t1.name as t1_name, t1.logo as t1_logo, t1.tag as t1_tag,
            t2.name as t2_name, t2.logo as t2_logo, t2.tag as t2_tag,
            w.name as winner_name,
            tr.name as tournament_name, tr.format as tournament_format,
            mr.team1_kills, mr.team2_kills, mr.team1_towers, mr.team2_towers, mr.mvp_user_id,
            mvpu.username as mvp_name, mvpu.avatar as mvp_avatar
     FROM tournament_matches m
     LEFT JOIN teams t1 ON m.team1_id = t1.id
     LEFT JOIN teams t2 ON m.team2_id = t2.id
     LEFT JOIN teams w ON m.winner_id = w.id
     LEFT JOIN tournaments tr ON m.tournament_id = tr.id
     LEFT JOIN match_results mr ON mr.match_id = m.id
     LEFT JOIN users mvpu ON mr.mvp_user_id = mvpu.id
     $where
     ORDER BY m.completed_at DESC, m.created_at DESC
     LIMIT 100",
    $params
);

// Preload player stats for all matches
$matchIds = array_column($matches, 'id');
$playerStats = [];
if (!empty($matchIds)) {
    $placeholders = implode(',', array_fill(0, count($matchIds), '?'));
    $stats = $db->fetchAll(
        "SELECT mps.*, u.username, u.avatar, u.ml_nickname
         FROM match_player_stats mps
         JOIN users u ON mps.user_id = u.id
         WHERE mps.match_id IN ($placeholders)
         ORDER BY mps.team_id, mps.kills DESC",
        $matchIds
    );
    foreach ($stats as $s) {
        $playerStats[$s['match_id']][] = $s;
    }
}

// Preload Comments
$matchComments = [];
if (!empty($matchIds)) {
    $placeholders = implode(',', array_fill(0, count($matchIds), '?'));
    $comments = $db->fetchAll(
        "SELECT mc.*, u.username, u.avatar 
         FROM match_comments mc
         JOIN users u ON mc.user_id = u.id
         WHERE mc.match_id IN ($placeholders)
         ORDER BY mc.created_at ASC",
        $matchIds
    );
    foreach ($comments as $c) {
        $matchComments[$c['match_id']][] = $c;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content match-history-page" data-filter-view="<?= htmlspecialchars($filterView, ENT_QUOTES) ?>">
    <div class="page-header">
        <h1 class="page-title">📜 Historial de Partidas</h1>
        <p class="page-subtitle">Resultados, estadísticas y detalles de todas las partidas jugadas</p>
    </div>

    <!-- Filters -->
    <div class="mh-filters">
        <div class="mh-view-tabs">
            <?php if ($myTeam): ?>
            <a href="?view=mine<?= $filterTournament ? '&tournament='.$filterTournament : '' ?>" class="mh-tab <?= $filterView === 'mine' ? 'active' : '' ?>">🎮 Mis Partidas</a>
            <?php endif; ?>
            <a href="?view=all<?= $filterTournament ? '&tournament='.$filterTournament : '' ?>" class="mh-tab <?= $filterView === 'all' ? 'active' : '' ?>">🌐 Todas</a>
        </div>
        <?php if (!empty($tournaments)): ?>
        <select class="form-control mh-tournament-filter" id="mhTournamentFilter">
            <option value="">-- Todos los Torneos --</option>
            <?php foreach ($tournaments as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $filterTournament == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>

    <!-- Stats Summary -->
    <?php if ($filterView === 'mine' && $myTeamId): 
        $myWins = count(array_filter($matches, fn($m) => $m['winner_id'] == $myTeamId));
        $myLosses = count($matches) - $myWins;
        $myWr = count($matches) > 0 ? round($myWins / count($matches) * 100) : 0;
    ?>
    <div class="mh-summary-row">
        <div class="mh-summary"><span class="mh-sum-num"><?= count($matches) ?></span><span class="mh-sum-label">Partidas</span></div>
        <div class="mh-summary mh-sum-win"><span class="mh-sum-num"><?= $myWins ?></span><span class="mh-sum-label">Victorias</span></div>
        <div class="mh-summary mh-sum-loss"><span class="mh-sum-num"><?= $myLosses ?></span><span class="mh-sum-label">Derrotas</span></div>
        <div class="mh-summary"><span class="mh-sum-num <?= $myWr >= 50 ? 'mh-sum-num--positive' : 'mh-sum-num--negative' ?>"><?= $myWr ?>%</span><span class="mh-sum-label">Win Rate</span></div>
    </div>
    <?php endif; ?>

    <!-- Match List -->
    <?php if (empty($matches)): ?>
    <div class="card mh-empty-card">
        <div class="mh-empty-icon">📜</div>
        <h3 class="mh-empty-title">Sin partidas completadas</h3>
        <p class="mh-empty-copy">Los resultados aparecerán aquí cuando se completen partidas de torneo.</p>
    </div>
    <?php else: ?>
    <div class="mh-match-list">
    <?php foreach ($matches as $idx => $m): 
        $isMyMatch = ($m['team1_id'] == $myTeamId || $m['team2_id'] == $myTeamId);
        $iWon = ($m['winner_id'] == $myTeamId && $myTeamId);
        $iLost = ($isMyMatch && !$iWon && $myTeamId);
        $hasStats = !empty($playerStats[$m['id']]);
    ?>
        <div class="mh-card <?= $iWon ? 'mh-card-win' : ($iLost ? 'mh-card-loss' : '') ?>">
            <!-- Top bar -->
            <div class="mh-card-top">
                <div class="mh-card-meta">
                    <span class="badge badge-purple mh-badge-compact"><?= htmlspecialchars($m['tournament_name'] ?? 'Torneo') ?></span>
                    <span class="mh-card-round">R<?= $m['round'] ?> · M<?= $m['match_number'] ?></span>
                    <?php if ($m['completed_at']): ?>
                    <span class="mh-card-date"><?= date('d M Y, H:i', strtotime($m['completed_at'])) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($isMyMatch && $myTeamId): ?>
                <span class="mh-result-pill <?= $iWon ? 'mh-win' : 'mh-loss' ?>"><?= $iWon ? '✅ Victoria' : '❌ Derrota' ?></span>
                <?php endif; ?>
            </div>

            <!-- Matchup -->
            <?php $comments = $matchComments[$m['id']] ?? []; ?>
            <div class="mh-matchup mh-matchup-toggle" data-toggle-match-detail="<?= $m['id'] ?>" title="Ver detalles y comentarios" role="button" tabindex="0">
                <div class="mh-team <?= $m['winner_id'] == $m['team1_id'] ? 'mh-team-winner' : '' ?>">
                    <?php if ($m['t1_logo']): ?>
                    <img src="<?= UPLOAD_URL . $m['t1_logo'] ?>" class="mh-team-logo" alt="">
                    <?php else: ?>
                    <div class="mh-team-logo-ph"><?= strtoupper(substr($m['t1_name'] ?? '?', 0, 2)) ?></div>
                    <?php endif; ?>
                    <div class="mh-team-info">
                        <div class="mh-team-name"><?= htmlspecialchars($m['t1_name'] ?? 'TBD') ?></div>
                        <?php if ($m['t1_tag']): ?><span class="badge badge-purple mh-badge-mini"><?= $m['t1_tag'] ?></span><?php endif; ?>
                    </div>
                </div>

                <div class="mh-score-center">
                    <div class="mh-scores">
                        <div class="mh-score-box <?= $m['winner_id'] == $m['team1_id'] ? 'mh-score-w' : '' ?>"><?= $m['team1_score'] ?></div>
                        <span class="mh-score-sep">:</span>
                        <div class="mh-score-box <?= $m['winner_id'] == $m['team2_id'] ? 'mh-score-w' : '' ?>"><?= $m['team2_score'] ?></div>
                    </div>
                    <?php if ($m['team1_kills'] !== null): ?>
                    <div class="mh-kills-row">
                        <?= $m['team1_kills'] ?> <span class="mh-kills-sep">-</span> <?= $m['team2_kills'] ?> kills
                    </div>
                    <?php endif; ?>
                    <?php if ($m['mvp_name']): ?>
                    <div class="mh-mvp-tag">⭐ MVP: <?= htmlspecialchars($m['mvp_name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mh-team mh-team-right <?= $m['winner_id'] == $m['team2_id'] ? 'mh-team-winner' : '' ?>">
                    <div class="mh-team-info mh-team-info-right">
                        <div class="mh-team-name"><?= htmlspecialchars($m['t2_name'] ?? 'TBD') ?></div>
                        <?php if ($m['t2_tag']): ?><span class="badge badge-purple mh-badge-mini"><?= $m['t2_tag'] ?></span><?php endif; ?>
                    </div>
                    <?php if ($m['t2_logo']): ?>
                    <img src="<?= UPLOAD_URL . $m['t2_logo'] ?>" class="mh-team-logo" alt="">
                    <?php else: ?>
                    <div class="mh-team-logo-ph"><?= strtoupper(substr($m['t2_name'] ?? '?', 0, 2)) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="mh-expand-hint">
                    <span class="mh-expand-meta">
                        <?php if (count($comments)): ?>
                        <span><?= count($comments) ?></span>
                        <span class="mh-expand-icon">💬</span>
                        <?php endif; ?>
                        ▾
                    </span>
                </div>
            </div>

            <!-- Expandable Stats & Comments -->
            <div class="mh-detail mh-detail-hidden" id="detail-<?= $m['id'] ?>">
                <?php if ($hasStats): ?>
                <div class="mh-detail-grid">
                    <?php 
                    $t1Stats = array_filter($playerStats[$m['id']], fn($s) => $s['team_id'] == $m['team1_id']);
                    $t2Stats = array_filter($playerStats[$m['id']], fn($s) => $s['team_id'] == $m['team2_id']);
                    ?>
                    <!-- Team 1 Stats -->
                    <div class="mh-detail-col">
                        <div class="mh-detail-header mh-blue"><?= htmlspecialchars($m['t1_name']) ?></div>
                        <?php foreach ($t1Stats as $ps): 
                            $heroImg = findHeroImage($ps['hero_used']);
                        ?>
                        <div class="mh-player-stat-row">
                            <?php if ($heroImg): ?>
                            <img src="<?= $heroImg ?>" class="mh-hero-icon" alt="">
                            <?php else: ?>
                            <div class="mh-hero-icon-ph">?</div>
                            <?php endif; ?>
                            <div class="mh-ps-name"><?= htmlspecialchars($ps['ml_nickname'] ?? $ps['username']) ?></div>
                            <div class="mh-ps-kda">
                                <span class="mh-k"><?= $ps['kills'] ?></span>/<span class="mh-d"><?= $ps['deaths'] ?></span>/<span class="mh-a"><?= $ps['assists'] ?></span>
                            </div>
                            <div class="mh-ps-kda-ratio"><?= $ps['deaths'] > 0 ? round(($ps['kills'] + $ps['assists']) / $ps['deaths'], 1) : ($ps['kills'] + $ps['assists']) ?> KDA</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Team 2 Stats -->
                    <div class="mh-detail-col">
                        <div class="mh-detail-header mh-red"><?= htmlspecialchars($m['t2_name']) ?></div>
                        <?php foreach ($t2Stats as $ps): 
                            $heroImg = findHeroImage($ps['hero_used']);
                        ?>
                        <div class="mh-player-stat-row">
                            <?php if ($heroImg): ?>
                            <img src="<?= $heroImg ?>" class="mh-hero-icon" alt="">
                            <?php else: ?>
                            <div class="mh-hero-icon-ph">?</div>
                            <?php endif; ?>
                            <div class="mh-ps-name"><?= htmlspecialchars($ps['ml_nickname'] ?? $ps['username']) ?></div>
                            <div class="mh-ps-kda">
                                <span class="mh-k"><?= $ps['kills'] ?></span>/<span class="mh-d"><?= $ps['deaths'] ?></span>/<span class="mh-a"><?= $ps['assists'] ?></span>
                            </div>
                            <div class="mh-ps-kda-ratio"><?= $ps['deaths'] > 0 ? round(($ps['kills'] + $ps['assists']) / $ps['deaths'], 1) : ($ps['kills'] + $ps['assists']) ?> KDA</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Comments Section -->
                <div class="mh-comments-section">
                    <div class="mh-comments-header">💬 Comentarios (<?= count($comments) ?>)</div>
                    <div class="mh-comments-list">
                        <?php foreach ($comments as $c): ?>
                        <div class="mh-comment">
                            <?php if ($c['avatar']): ?>
                            <img src="<?= UPLOAD_URL . $c['avatar'] ?>" class="mh-comment-avatar" alt="">
                            <?php else: ?>
                            <div class="mh-comment-avatar-ph"><?= strtoupper(substr($c['username'], 0, 1)) ?></div>
                            <?php endif; ?>
                            <div class="mh-comment-body">
                                <div class="mh-comment-meta">
                                    <div class="mh-comment-author-row">
                                        <a href="<?= url('user/view/' . $c['user_id']) ?>" class="mh-comment-author mh-comment-author-link"><?= htmlspecialchars($c['username']) ?></a>
                                        <?php if (isAdmin() || isSuperAdmin()): ?>
                                        <form method="POST" action="<?= url('match-history') ?>" class="mh-comment-delete-form">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete_comment">
                                            <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="mh-comment-delete-btn" data-confirm-delete="¿Seguro quieres borrar este comentario?" title="Borrar comentario">❌</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                    <span class="mh-comment-date"><?= timeAgo($c['created_at']) ?></span>
                                </div>
                                <div class="mh-comment-text"><?= nl2br(htmlspecialchars($c['comment'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($comments)): ?>
                        <div class="mh-comments-empty">Sé el primero en comentar.</div>
                        <?php endif; ?>
                    </div>
                    <?php if (isLoggedIn()): ?>
                    <?php
                    $formAction = url('match-history') . '?' . http_build_query(array_filter([
                        'view'       => $filterView,
                        'tournament' => $filterTournament ?: null,
                    ]));
                    ?>
                    <form method="POST" action="<?= $formAction ?>" class="mh-comment-form">
                        <input type="hidden" name="action" value="add_comment">
                        <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
                        <input type="text" name="comment" class="form-control" placeholder="Escribe un comentario..." required autocomplete="off">
                        <button type="submit" class="btn btn-primary mh-comment-submit-btn">Enviar</button>
                    </form>
                    <?php else: ?>
                    <div class="mh-comments-login-prompt">
                        <a href="<?= url('login') ?>" class="mh-comments-login-link">Inicia sesión</a> para comentar.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
</div>

<?php
/** Helper: find hero image by name across role folders */
function findHeroImage($heroName) {
    if (!$heroName) return '';
    $heroNameLower = strtolower(trim($heroName));
    foreach (ML_ROLE_FOLDERS as $role => $folderName) {
        $dir = __DIR__ . "/../assets/heroes_img/{$folderName}/";
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['..', '.']);
            foreach ($files as $f) {
                if (strtolower(pathinfo($f, PATHINFO_FILENAME)) == $heroNameLower) {
                    return url("assets/heroes_img/{$folderName}/{$f}");
                }
            }
        }
    }
    return '';
}
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
