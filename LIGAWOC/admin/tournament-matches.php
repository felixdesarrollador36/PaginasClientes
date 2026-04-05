<?php
/**
 * Liga WOC - Admin Tournament Matches Management (Redesigned)
 */

if (!isAdmin()) redirect('dashboard');

$tournId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$tournId) {
    setFlash('error', 'Torneo no especificado.');
    redirect('admin/tournaments');
}

$db = Database::getInstance();
$tournCtrl = new TournamentController();
$tournament = $tournCtrl->getTournament($tournId);

if (!$tournament) {
    setFlash('error', 'Torneo no encontrado.');
    redirect('admin/tournaments');
}

$pageTitle = 'Partidas: ' . $tournament['name'];
$page = 'admin';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'schedule') {
        $matchId = intval($_POST['match_id']);
        $scheduledAt = $_POST['scheduled_at'] ?: null;
        
        $db->update("UPDATE tournament_matches SET scheduled_at = ?, status = 'scheduled' WHERE id = ?", [$scheduledAt, $matchId]);
        
        // Auto-notify teams
        $notifCtrl = new NotificationController();
        $notifCtrl->notifyMatchScheduled($matchId);
        
        setFlash('success', 'Partida programada. Equipos notificados.');
        redirect('admin/tournament-matches?id=' . $tournId);
    }
    
    if ($action === 'submit_results') {
        $matchId = intval($_POST['match_id']);
        $winnerId = intval($_POST['winner_id']);
        $t1Score = intval($_POST['team1_score']);
        $t2Score = intval($_POST['team2_score']);
        $t1Kills = intval($_POST['team1_kills']);
        $t2Kills = intval($_POST['team2_kills']);
        $t1Towers = intval($_POST['team1_towers']);
        $t2Towers = intval($_POST['team2_towers']);
        
        $match = $db->fetch("SELECT team1_id, team2_id FROM tournament_matches WHERE id = ?", [$matchId]);
        $loserId = ($match['team1_id'] == $winnerId) ? $match['team2_id'] : $match['team1_id'];
        
        $db->getConnection()->beginTransaction();
        try {
            $db->update(
                "UPDATE tournament_matches SET winner_id = ?, loser_id = ?, team1_score = ?, team2_score = ?, status = 'completed', completed_at = NOW() WHERE id = ?",
                [$winnerId, $loserId, $t1Score, $t2Score, $matchId]
            );
            
            $db->insert(
                "INSERT INTO match_results (match_id, team1_score, team2_score, team1_kills, team2_kills, team1_towers, team2_towers, mvp_user_id, verified_by, verification_status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'verified')",
                [$matchId, $t1Score, $t2Score, $t1Kills, $t2Kills, $t1Towers, $t2Towers, (intval($_POST['mvp_user_id'] ?? 0) ?: null), currentUserId()]
            );
            
            $tournCtrl->advanceWinner($matchId, $winnerId);
            
            if (isset($_POST['stats']) && is_array($_POST['stats'])) {
                foreach ($_POST['stats'] as $userId => $stat) {
                    $hero = sanitize($stat['hero']);
                    $k = intval($stat['k']);
                    $d = intval($stat['d']);
                    $a = intval($stat['a']);
                    $tId = intval($stat['team_id']);
                    
                    if ($hero) {
                        $db->insert(
                            "INSERT INTO match_player_stats (match_id, user_id, team_id, hero_used, kills, deaths, assists) VALUES (?, ?, ?, ?, ?, ?, ?)",
                            [$matchId, $userId, $tId, $hero, $k, $d, $a]
                        );
                    }
                }
            }
            
            $db->getConnection()->commit();
            
            // Auto-notify teams
            $notifCtrl = new NotificationController();
            $notifCtrl->notifyMatchCompleted($matchId);
            $notifCtrl->notifyBracketAdvance($winnerId, $tournId);
            
            setFlash('success', 'Resultados guardados. Equipos notificados. Bracket actualizado.');
        } catch (Exception $e) {
            $db->getConnection()->rollBack();
            setFlash('error', 'Error al guardar los resultados: ' . $e->getMessage());
        }
        
        redirect('admin/tournament-matches?id=' . $tournId);
    }
}

// Fetch all matches
$matches = $db->fetchAll(
    "SELECT m.*, 
            t1.name as t1_name, t1.logo as t1_logo, t1.tag as t1_tag,
            t2.name as t2_name, t2.logo as t2_logo, t2.tag as t2_tag,
            w.name as winner_name
     FROM tournament_matches m
     LEFT JOIN teams t1 ON m.team1_id = t1.id
     LEFT JOIN teams t2 ON m.team2_id = t2.id
     LEFT JOIN teams w ON m.winner_id = w.id
     WHERE m.tournament_id = ?
     ORDER BY m.bracket_type ASC, m.round ASC, m.match_number ASC",
    [$tournId]
);

// Separate group and knockout matches
$groupMatches = [];
$knockoutMatches = [];
foreach ($matches as $m) {
    if ($m['bracket_type'] === 'group') {
        $groupMatches[$m['group_name']][] = $m;
    } else {
        $knockoutMatches[] = $m;
    }
}

// Stats summary
$totalMatches = count($matches);
$completedMatches = count(array_filter($matches, fn($m) => $m['status'] === 'completed'));
$pendingMatches = count(array_filter($matches, fn($m) => $m['status'] === 'pending' && $m['team1_id'] && $m['team2_id']));
$scheduledMatches = count(array_filter($matches, fn($m) => $m['status'] === 'scheduled'));

$teamsPlayers = [];
foreach ($matches as $m) {
    foreach ([$m['team1_id'], $m['team2_id']] as $tid) {
        if ($tid && !isset($teamsPlayers[$tid])) {
            $teamsPlayers[$tid] = $db->fetchAll("SELECT tm.*, u.username as name FROM team_members tm JOIN users u ON tm.user_id = u.id WHERE tm.team_id = ?", [$tid]);
        }
    }
}

// Load heroes from DB (unified with admin panel)
$_allHeroes = $db->fetchAll("SELECT id, name, role, image_url FROM ml_heroes ORDER BY name ASC");
$_roleMap = [
    'Tirador' => 'adc', 'TIRADOR' => 'adc',
    'Mago' => 'mage',   'MAGO' => 'mage',
    'Tanque' => 'tank', 'TANQUE' => 'tank', 'Tanque/Apoyo' => 'tank', 'Tanque/Combatiente' => 'tank',
    'Asesino' => 'assassin', 'ASESINO' => 'assassin',
    'Combatiente' => 'fighter', 'COMBATIENTE' => 'fighter',
    'Apoyo' => 'support', 'APOYO' => 'support',
];
$heroesByRole = []; // rolKey -> [['name'=>, 'img'=>], ...]
foreach ($_allHeroes as $h) {
    $primaryRole = explode('/', $h['role'])[0];
    $roleKey = $_roleMap[$primaryRole] ?? $_roleMap[$h['role']] ?? null;
    if (!$roleKey) continue;
    $heroesByRole[$roleKey][] = [
        'name' => $h['name'],
        'img'  => $h['image_url'] ? BASE_URL . ltrim($h['image_url'], '/') : null,
    ];
}

// Active filter
$activeFilter = $_GET['filter'] ?? 'all';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-wrapper">
<div class="main-content">
    <!-- Breadcrumb -->
    <a href="<?= url('admin/tournaments') ?>" style="display:inline-flex;align-items:center;gap:6px;color:var(--text-muted);font-size:0.82rem;margin-bottom:16px;text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='var(--accent-main)'" onmouseout="this.style.color='var(--text-muted)'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Volver a Torneos
    </a>

    <!-- Tournament Header -->
    <div class="tm-header">
        <div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                <h1 class="page-title" style="margin:0;">⚔️ <?= htmlspecialchars($tournament['name']) ?></h1>
                <span class="tournament-status <?= $tournament['status'] ?>"><?php
                    $statusLabels = ['draft' => 'Borrador', 'registration' => 'Inscripciones', 'ready' => 'Listo', 'in_progress' => 'En Curso', 'completed' => 'Finalizado'];
                    echo $statusLabels[$tournament['status']] ?? $tournament['status'];
                ?></span>
                <?php if (!empty($tournament['is_main_tournament'])): ?>
                <span class="badge badge-yellow" style="font-size:0.72rem;">⭐ PRINCIPAL</span>
                <?php endif; ?>
            </div>
            <p style="color:var(--text-muted);font-size:0.82rem;margin:0;"><?= TOURNAMENT_FORMATS[$tournament['format']] ?? $tournament['format'] ?> · <?= $tournament['registered_teams'] ?>/<?= $tournament['max_teams'] ?> equipos</p>
        </div>
        <a href="<?= url('tournaments/view/' . $tournId) ?>" class="btn btn-sm btn-secondary" target="_blank">
            👁️ Ver Torneo Público
        </a>
    </div>

    <!-- Stats Overview -->
    <div class="tm-stats-row">
        <div class="tm-stat-card">
            <div class="tm-stat-number"><?= $totalMatches ?></div>
            <div class="tm-stat-label">Total Partidas</div>
        </div>
        <div class="tm-stat-card tm-stat-completed">
            <div class="tm-stat-number"><?= $completedMatches ?></div>
            <div class="tm-stat-label">Completadas</div>
        </div>
        <div class="tm-stat-card tm-stat-pending">
            <div class="tm-stat-number"><?= $pendingMatches ?></div>
            <div class="tm-stat-label">Pendientes</div>
        </div>
        <div class="tm-stat-card tm-stat-scheduled">
            <div class="tm-stat-number"><?= $scheduledMatches ?></div>
            <div class="tm-stat-label">Programadas</div>
        </div>
        <?php if ($totalMatches > 0): ?>
        <div class="tm-stat-card" style="flex:2;">
            <div class="tm-progress-bar">
                <div class="tm-progress-fill" style="width:<?= $totalMatches > 0 ? round($completedMatches / $totalMatches * 100) : 0 ?>%;"></div>
            </div>
            <div class="tm-stat-label"><?= $totalMatches > 0 ? round($completedMatches / $totalMatches * 100) : 0 ?>% completado</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filter Tabs -->
    <div class="tm-filter-tabs">
        <a href="?id=<?= $tournId ?>&filter=all" class="tm-filter-tab <?= $activeFilter === 'all' ? 'active' : '' ?>">Todas</a>
        <a href="?id=<?= $tournId ?>&filter=pending" class="tm-filter-tab <?= $activeFilter === 'pending' ? 'active' : '' ?>">⏳ Pendientes</a>
        <a href="?id=<?= $tournId ?>&filter=scheduled" class="tm-filter-tab <?= $activeFilter === 'scheduled' ? 'active' : '' ?>">📅 Programadas</a>
        <a href="?id=<?= $tournId ?>&filter=completed" class="tm-filter-tab <?= $activeFilter === 'completed' ? 'active' : '' ?>">✅ Completadas</a>
        <?php if (!empty($groupMatches)): ?>
        <a href="?id=<?= $tournId ?>&filter=groups" class="tm-filter-tab <?= $activeFilter === 'groups' ? 'active' : '' ?>">🏟️ Fase de Grupos</a>
        <?php endif; ?>
        <?php if (!empty($knockoutMatches)): ?>
        <a href="?id=<?= $tournId ?>&filter=knockout" class="tm-filter-tab <?= $activeFilter === 'knockout' ? 'active' : '' ?>">🏆 Llaves</a>
        <?php endif; ?>
    </div>

    <!-- GROUP STAGE MATCHES -->
    <?php if (!empty($groupMatches) && in_array($activeFilter, ['all', 'groups', 'pending', 'scheduled', 'completed'])): ?>
    <div class="tm-section-header">
        <h2>🏟️ Fase de Grupos</h2>
        <span class="badge badge-purple"><?= array_sum(array_map('count', $groupMatches)) ?> partidas</span>
    </div>

    <?php foreach ($groupMatches as $groupName => $gMatches): ?>
        <div class="tm-group-header">Grupo <?= htmlspecialchars($groupName) ?></div>
        <div class="tm-match-list">
        <?php foreach ($gMatches as $m): 
            if ($activeFilter === 'pending' && $m['status'] !== 'pending') continue;
            if ($activeFilter === 'scheduled' && $m['status'] !== 'scheduled') continue;
            if ($activeFilter === 'completed' && $m['status'] !== 'completed') continue;
        ?>
            <?php echo renderMatchCard($m, $teamsPlayers); ?>
        <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- KNOCKOUT MATCHES -->
    <?php if (!empty($knockoutMatches) && in_array($activeFilter, ['all', 'knockout', 'pending', 'scheduled', 'completed'])): ?>
    <div class="tm-section-header">
        <h2>🏆 Llaves Eliminatorias</h2>
        <span class="badge badge-orange"><?= count($knockoutMatches) ?> partidas</span>
    </div>

    <div class="tm-match-list">
    <?php foreach ($knockoutMatches as $m): 
        if ($activeFilter === 'pending' && $m['status'] !== 'pending') continue;
        if ($activeFilter === 'scheduled' && $m['status'] !== 'scheduled') continue;
        if ($activeFilter === 'completed' && $m['status'] !== 'completed') continue;
    ?>
        <?php echo renderMatchCard($m, $teamsPlayers); ?>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ALL (no groups/knockout separation) -->
    <?php if (empty($groupMatches) && empty($knockoutMatches) && !empty($matches) && in_array($activeFilter, ['all', 'pending', 'scheduled', 'completed'])): ?>
    <div class="tm-match-list">
    <?php foreach ($matches as $m): 
        if ($activeFilter === 'pending' && $m['status'] !== 'pending') continue;
        if ($activeFilter === 'scheduled' && $m['status'] !== 'scheduled') continue;
        if ($activeFilter === 'completed' && $m['status'] !== 'completed') continue;
    ?>
        <?php echo renderMatchCard($m, $teamsPlayers); ?>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($matches)): ?>
    <div class="card" style="text-align:center;padding:60px 20px;">
        <div style="font-size:3rem;margin-bottom:10px;">🎮</div>
        <h3 style="color:var(--text-secondary);margin-bottom:4px;">No hay partidas generadas</h3>
        <p style="color:var(--text-muted);font-size:0.85rem;">Genera el bracket desde la página de administración de torneos para crear las partidas.</p>
        <a href="<?= url('admin/tournaments') ?>" class="btn btn-primary" style="margin-top:12px;">Ir a Torneos</a>
    </div>
    <?php endif; ?>

</div>
</div>

<?php
/**
 * Renders a single match card with inline schedule/result forms.
 */
function renderMatchCard($m, $teamsPlayers) {
    $isCompleted = $m['status'] === 'completed';
    $hasBothTeams = $m['team1_id'] && $m['team2_id'];
    $canAct = $hasBothTeams && !$isCompleted;
    
    $statusClass = match($m['status']) {
        'completed' => 'tm-status-completed',
        'scheduled' => 'tm-status-scheduled',
        'live' => 'tm-status-live',
        default => 'tm-status-pending',
    };
    $statusLabel = match($m['status']) {
        'completed' => '✅ Completada',
        'scheduled' => '📅 Programada',
        'live' => '🔴 EN VIVO',
        default => '⏳ Pendiente',
    };
    
    $roundLabel = 'R' . $m['round'] . ' — M' . $m['match_number'];
    if ($m['bracket_type'] === 'group' && $m['group_name']) {
        $roundLabel = 'Grupo ' . $m['group_name'] . ' — M' . $m['match_number'];
    }
    
    ob_start();
?>
    <div class="tm-match-card <?= $statusClass ?>" id="match-card-<?= $m['id'] ?>">
        <div class="tm-match-top">
            <div class="tm-match-meta">
                <span class="tm-match-round"><?= $roundLabel ?></span>
                <span class="tm-match-status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                <?php if ($m['scheduled_at']): ?>
                <span class="tm-match-date">📅 <?= date('d M, H:i', strtotime($m['scheduled_at'])) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($canAct): ?>
            <div class="tm-match-actions">
                <button class="btn btn-sm btn-glass" onclick="togglePanel('schedule-<?= $m['id'] ?>')">📅 Programar</button>
                <button class="btn btn-sm btn-primary" onclick="togglePanel('result-<?= $m['id'] ?>')">📝 Resultados</button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Matchup Display -->
        <div class="tm-matchup">
            <div class="tm-team tm-team-left <?= $m['winner_id'] == $m['team1_id'] && $m['winner_id'] ? 'tm-team-winner' : '' ?>">
                <?php if ($m['t1_logo']): ?>
                <img src="<?= UPLOAD_URL . $m['t1_logo'] ?>" class="tm-team-logo" alt="">
                <?php else: ?>
                <div class="tm-team-logo-placeholder">🔵</div>
                <?php endif; ?>
                <div class="tm-team-info">
                    <div class="tm-team-name"><?= $m['t1_name'] ? htmlspecialchars($m['t1_name']) : 'TBD' ?></div>
                    <?php if ($m['t1_tag']): ?><span class="badge badge-purple" style="font-size:0.6rem;"><?= $m['t1_tag'] ?></span><?php endif; ?>
                </div>
                <?php if ($isCompleted): ?>
                <div class="tm-team-score <?= $m['winner_id'] == $m['team1_id'] ? 'tm-score-winner' : 'tm-score-loser' ?>"><?= $m['team1_score'] ?></div>
                <?php endif; ?>
            </div>

            <div class="tm-vs">
                <?php if ($isCompleted): ?>
                    <?php if ($m['winner_name']): ?>
                    <div class="tm-winner-tag">🏆 <?= htmlspecialchars($m['winner_name']) ?></div>
                    <?php endif; ?>
                <?php else: ?>
                <span>VS</span>
                <?php endif; ?>
            </div>

            <div class="tm-team tm-team-right <?= $m['winner_id'] == $m['team2_id'] && $m['winner_id'] ? 'tm-team-winner' : '' ?>">
                <?php if ($isCompleted): ?>
                <div class="tm-team-score <?= $m['winner_id'] == $m['team2_id'] ? 'tm-score-winner' : 'tm-score-loser' ?>"><?= $m['team2_score'] ?></div>
                <?php endif; ?>
                <div class="tm-team-info" style="text-align:right;">
                    <div class="tm-team-name"><?= $m['t2_name'] ? htmlspecialchars($m['t2_name']) : 'TBD' ?></div>
                    <?php if ($m['t2_tag']): ?><span class="badge badge-purple" style="font-size:0.6rem;"><?= $m['t2_tag'] ?></span><?php endif; ?>
                </div>
                <?php if ($m['t2_logo']): ?>
                <img src="<?= UPLOAD_URL . $m['t2_logo'] ?>" class="tm-team-logo" alt="">
                <?php else: ?>
                <div class="tm-team-logo-placeholder">🔴</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Inline Schedule Form -->
        <?php if ($canAct): ?>
        <div class="tm-panel" id="schedule-<?= $m['id'] ?>" style="display:none;">
            <form method="POST" class="tm-schedule-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="schedule">
                <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
                <div class="form-group mb-0" style="flex:1;">
                    <label class="form-label" style="font-size:0.75rem;">Fecha y Hora</label>
                    <input type="datetime-local" name="scheduled_at" class="form-control" value="<?= $m['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($m['scheduled_at'])) : '' ?>" required>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">💾 Guardar</button>
                <button type="button" class="btn btn-sm btn-glass" onclick="togglePanel('schedule-<?= $m['id'] ?>')">Cancelar</button>
            </form>
        </div>

        <!-- Inline Results Form -->
        <div class="tm-panel tm-results-panel" id="result-<?= $m['id'] ?>" style="display:none;">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="submit_results">
                <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
                
                <h4 class="tm-panel-title">📝 Registrar Resultados — M<?= $m['match_number'] ?></h4>
                
                <!-- Score Section -->
                <div class="tm-score-section">
                    <div class="tm-score-team tm-score-team-blue">
                        <h5>🔵 <?= htmlspecialchars($m['t1_name']) ?></h5>
                        <div class="tm-score-inputs">
                            <div class="tm-input-group">
                                <label>Victorias</label>
                                <input type="number" name="team1_score" id="t1_score_<?= $m['id'] ?>" class="form-control" value="0" min="0" required>
                            </div>
                            <div class="tm-input-group">
                                <label>Kills</label>
                                <input type="number" name="team1_kills" id="t1_kills_<?= $m['id'] ?>" class="form-control" value="0" required>
                            </div>
                            <div class="tm-input-group">
                                <label>Torres</label>
                                <input type="number" name="team1_towers" id="t1_towers_<?= $m['id'] ?>" class="form-control" value="0" max="9" required>
                            </div>
                        </div>
                    </div>
                    <div class="tm-score-vs">VS</div>
                    <div class="tm-score-team tm-score-team-red">
                        <h5>🔴 <?= htmlspecialchars($m['t2_name']) ?></h5>
                        <div class="tm-score-inputs">
                            <div class="tm-input-group">
                                <label>Victorias</label>
                                <input type="number" name="team2_score" id="t2_score_<?= $m['id'] ?>" class="form-control" value="0" min="0" required>
                            </div>
                            <div class="tm-input-group">
                                <label>Kills</label>
                                <input type="number" name="team2_kills" id="t2_kills_<?= $m['id'] ?>" class="form-control" value="0" required>
                            </div>
                            <div class="tm-input-group">
                                <label>Torres</label>
                                <input type="number" name="team2_towers" id="t2_towers_<?= $m['id'] ?>" class="form-control" value="0" max="9" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Winner -->
                <div class="tm-winner-section">
                    <label class="form-label">🏆 ¿Quién ganó? *</label>
                    <select name="winner_id" id="winner_select_<?= $m['id'] ?>" class="form-control" required>
                        <option value="">-- Seleccionar Ganador --</option>
                        <option value="<?= $m['team1_id'] ?>"><?= htmlspecialchars($m['t1_name']) ?></option>
                        <option value="<?= $m['team2_id'] ?>"><?= htmlspecialchars($m['t2_name']) ?></option>
                    </select>
                </div>

                <!-- MVP -->
                <div class="tm-winner-section" style="margin-top:10px;">
                    <label class="form-label">⭐ MVP de la partida (opcional)</label>
                    <select name="mvp_user_id" class="form-control">
                        <option value="">-- Sin MVP --</option>
                        <?php 
                        $allPlayers = array_merge($teamsPlayers[$m['team1_id']] ?? [], $teamsPlayers[$m['team2_id']] ?? []);
                        foreach ($allPlayers as $pl): ?>
                        <option value="<?= $pl['user_id'] ?>"><?= htmlspecialchars($pl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Player Stats -->
                <div class="tm-player-stats">
                    <h4 class="tm-panel-subtitle">📊 Estadísticas Individuales (Selecciona exactamente los 5 jugadores que participaron por equipo)</h4>
                    <div class="tm-stats-grid">
                        <div class="tm-stats-col">
                            <h6 class="tm-stats-team-header tm-blue"><?= htmlspecialchars($m['t1_name']) ?> <span class="badge badge-purple" id="t1_count_<?= $m['id'] ?>">0/5 Seleccionados</span></h6>
                            <?php $t1Players = $teamsPlayers[$m['team1_id']] ?? []; ?>
                            <?php foreach ($t1Players as $p): ?>
                            <div class="tm-player-row">
                                <label style="display:flex; align-items:center; gap:6px; cursor:pointer;" title="<?= htmlspecialchars($p['name']) ?>">
                                    <input type="checkbox" class="tm-player-checkbox t1-chk-<?= $m['id'] ?>" onchange="togglePlayerStats(this, '<?= $m['id'] ?>_<?= $p['user_id'] ?>', <?= $m['id'] ?>, 't1')">
                                    <span class="tm-player-name"><?= htmlspecialchars($p['name']) ?></span>
                                </label>
                                
                                <div class="tm-player-inputs" id="inputs_<?= $m['id'] ?>_<?= $p['user_id'] ?>" style="display:none; align-items:center; gap:5px;">
                                    <input type="hidden" name="stats[<?= $p['user_id'] ?>][team_id]" value="<?= $m['team1_id'] ?>" disabled>
                                    <input type="hidden" name="stats[<?= $p['user_id'] ?>][hero]" id="heroInput_<?= $m['id'] ?>_<?= $p['user_id'] ?>" disabled required>
                                    <button type="button" class="tm-hero-btn" onclick="openAdminHeroPicker('<?= $m['id'] ?>_<?= $p['user_id'] ?>')">
                                        <img id="heroImg_<?= $m['id'] ?>_<?= $p['user_id'] ?>" src="" style="display:none;">
                                        <span id="heroText_<?= $m['id'] ?>_<?= $p['user_id'] ?>">+ Héroe</span>
                                    </button>
                                    <input type="number" name="stats[<?= $p['user_id'] ?>][k]" class="tm-kda-input k-input-t1-<?= $m['id'] ?>" oninput="calcMatchStats(<?= $m['id'] ?>)" placeholder="K" min="0" disabled required>
                                    <input type="number" name="stats[<?= $p['user_id'] ?>][d]" class="tm-kda-input" placeholder="D" min="0" disabled required>
                                    <input type="number" name="stats[<?= $p['user_id'] ?>][a]" class="tm-kda-input" placeholder="A" min="0" disabled required>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="tm-stats-col">
                            <h6 class="tm-stats-team-header tm-red"><?= htmlspecialchars($m['t2_name']) ?> <span class="badge badge-purple" id="t2_count_<?= $m['id'] ?>">0/5 Seleccionados</span></h6>
                            <?php $t2Players = $teamsPlayers[$m['team2_id']] ?? []; ?>
                            <?php foreach ($t2Players as $p): ?>
                            <div class="tm-player-row">
                                <label style="display:flex; align-items:center; gap:6px; cursor:pointer;" title="<?= htmlspecialchars($p['name']) ?>">
                                    <input type="checkbox" class="tm-player-checkbox t2-chk-<?= $m['id'] ?>" onchange="togglePlayerStats(this, '<?= $m['id'] ?>_<?= $p['user_id'] ?>', <?= $m['id'] ?>, 't2')">
                                    <span class="tm-player-name"><?= htmlspecialchars($p['name']) ?></span>
                                </label>

                                <div class="tm-player-inputs" id="inputs_<?= $m['id'] ?>_<?= $p['user_id'] ?>" style="display:none; align-items:center; gap:5px;">
                                    <input type="hidden" name="stats[<?= $p['user_id'] ?>][team_id]" value="<?= $m['team2_id'] ?>" disabled>
                                    <input type="hidden" name="stats[<?= $p['user_id'] ?>][hero]" id="heroInput_<?= $m['id'] ?>_<?= $p['user_id'] ?>" disabled required>
                                    <button type="button" class="tm-hero-btn" onclick="openAdminHeroPicker('<?= $m['id'] ?>_<?= $p['user_id'] ?>')">
                                        <img id="heroImg_<?= $m['id'] ?>_<?= $p['user_id'] ?>" src="" style="display:none;">
                                        <span id="heroText_<?= $m['id'] ?>_<?= $p['user_id'] ?>">+ Héroe</span>
                                    </button>
                                    <input type="number" name="stats[<?= $p['user_id'] ?>][k]" class="tm-kda-input k-input-t2-<?= $m['id'] ?>" oninput="calcMatchStats(<?= $m['id'] ?>)" placeholder="K" min="0" disabled required>
                                    <input type="number" name="stats[<?= $p['user_id'] ?>][d]" class="tm-kda-input" placeholder="D" min="0" disabled required>
                                    <input type="number" name="stats[<?= $p['user_id'] ?>][a]" class="tm-kda-input" placeholder="A" min="0" disabled required>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="tm-form-footer">
                    <button type="button" class="btn btn-glass" onclick="togglePanel('result-<?= $m['id'] ?>')">Cancelar</button>
                    <button type="submit" class="btn btn-primary" onclick="return validateMatchSubmission(<?= $m['id'] ?>)">✅ Guardar Resultados Oficiales</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
<?php
    return ob_get_clean();
}
?>

<!-- HERO PICKER MODAL -->
<div id="heroModal" class="hero-modal-overlay">
    <div class="hero-modal">
        <div class="hero-modal-header">
            <h3>SELECCIONA UN HÉROE</h3>
            <button type="button" class="hero-modal-close" onclick="closeHeroPicker()">×</button>
        </div>
        <div class="hero-modal-search">
            <input type="text" id="heroSearch" class="form-control" placeholder="Buscar héroe..." oninput="filterHeroes()">
        </div>
        <div class="hero-modal-tabs">
            <button type="button" class="hero-tab active" onclick="filterByRole('all', this)">TODOS</button>
            <?php foreach (ML_ROLE_IMAGES as $role => $img): ?>
                <button type="button" class="hero-tab" onclick="filterByRole('<?= $role ?>', this)"><?= strtoupper($role) ?></button>
            <?php endforeach; ?>
        </div>
        <div class="hero-modal-grid">
            <?php
            foreach ($heroesByRole as $role => $heroes) {
                foreach ($heroes as $hero) {
                    $imgUrl = $hero['img'] ?? '';
                    $heroName = htmlspecialchars($hero['name'], ENT_QUOTES);
                    $imgEsc = htmlspecialchars($imgUrl);
                    echo "<div class='hero-pick-card' data-role='{$role}' data-name='" . strtolower($hero['name']) . "' onclick=\"pickHero('{$heroName}', '{$imgEsc}')\">"
                        . "<img src='{$imgEsc}' alt='{$heroName}' loading='lazy' onerror=\"this.src=''\">"
                        . "<span>{$heroName}</span></div>";
                }
            }
            ?>
        </div>
    </div>
</div>

<style>
/* ===== TOURNAMENT MATCHES REDESIGN ===== */
.tm-header { display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px; }
.tm-stats-row { display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap; }
.tm-stat-card { background:var(--bg-card);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:14px 18px;flex:1;min-width:100px;text-align:center; }
.tm-stat-number { font-family:var(--font-heading);font-size:1.6rem;font-weight:800;color:var(--text-primary); }
.tm-stat-label { font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-top:2px; }
.tm-stat-completed .tm-stat-number { color:var(--success); }
.tm-stat-pending .tm-stat-number { color:var(--warning,#f5a623); }
.tm-stat-scheduled .tm-stat-number { color:var(--info,#3b82f6); }
.tm-progress-bar { height:8px;background:rgba(255,255,255,0.06);border-radius:4px;overflow:hidden;margin-bottom:6px; }
.tm-progress-fill { height:100%;background:linear-gradient(90deg,var(--accent-main),var(--success));border-radius:4px;transition:width 0.4s ease; }

/* Filter Tabs */
.tm-filter-tabs { display:flex;gap:4px;margin-bottom:20px;overflow-x:auto;padding-bottom:4px; }
.tm-filter-tab { padding:7px 14px;border-radius:8px;font-size:0.78rem;font-weight:600;color:var(--text-muted);text-decoration:none;white-space:nowrap;border:1px solid rgba(255,255,255,0.06);transition:all 0.2s; }
.tm-filter-tab:hover { background:rgba(255,255,255,0.04);color:var(--text-secondary); }
.tm-filter-tab.active { background:rgba(var(--accent-rgb,124,92,252),0.15);border-color:rgba(var(--accent-rgb,124,92,252),0.4);color:#fff; }

/* Section Headers */
.tm-section-header { display:flex;align-items:center;gap:10px;margin:24px 0 12px;padding-bottom:8px;border-bottom:1px solid rgba(255,255,255,0.06); }
.tm-section-header h2 { font-size:1.1rem;font-family:var(--font-heading);margin:0; }
.tm-group-header { font-size:0.82rem;font-weight:700;color:var(--accent-main);text-transform:uppercase;letter-spacing:1px;padding:10px 0 6px;margin-top:8px; }

/* Match List */
.tm-match-list { display:flex;flex-direction:column;gap:10px;margin-bottom:16px; }

/* MATCH CARD */
.tm-match-card { background:var(--bg-card);border:1px solid rgba(255,255,255,0.06);border-radius:14px;overflow:hidden;transition:border-color 0.2s,box-shadow 0.2s; }
.tm-match-card:hover { border-color:rgba(255,255,255,0.12);box-shadow:0 4px 24px rgba(0,0,0,0.2); }
.tm-match-card.tm-status-completed { border-left:3px solid var(--success); }
.tm-match-card.tm-status-scheduled { border-left:3px solid var(--info,#3b82f6); }
.tm-match-card.tm-status-live { border-left:3px solid var(--danger);animation:pulse-border 2s infinite; }
.tm-match-card.tm-status-pending { border-left:3px solid rgba(255,255,255,0.1); }

.tm-match-top { display:flex;justify-content:space-between;align-items:center;padding:10px 16px 0;flex-wrap:wrap;gap:6px; }
.tm-match-meta { display:flex;align-items:center;gap:8px;flex-wrap:wrap; }
.tm-match-round { font-size:0.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px; }
.tm-match-status-badge { font-size:0.68rem;font-weight:600;padding:2px 8px;border-radius:6px; }
.tm-match-status-badge.tm-status-completed { background:rgba(0,214,143,0.1);color:var(--success); }
.tm-match-status-badge.tm-status-scheduled { background:rgba(59,130,246,0.1);color:var(--info,#3b82f6); }
.tm-match-status-badge.tm-status-live { background:rgba(255,51,102,0.1);color:var(--danger); }
.tm-match-status-badge.tm-status-pending { background:rgba(255,255,255,0.04);color:var(--text-muted); }
.tm-match-date { font-size:0.72rem;color:var(--text-muted); }
.tm-match-actions { display:flex;gap:6px; }

/* Matchup Layout */
.tm-matchup { display:flex;align-items:center;justify-content:center;padding:14px 20px 16px;gap:16px; }
.tm-team { display:flex;align-items:center;gap:10px;flex:1; }
.tm-team-right { justify-content:flex-end; }
.tm-team-logo { width:40px;height:40px;border-radius:10px;object-fit:cover;border:2px solid rgba(255,255,255,0.08); }
.tm-team-logo-placeholder { width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,0.04);display:flex;align-items:center;justify-content:center;font-size:1.2rem; }
.tm-team-name { font-weight:700;font-size:0.92rem; }
.tm-team-winner { }
.tm-team-winner .tm-team-name { color:var(--success); }
.tm-team-score { font-family:var(--font-heading);font-size:1.8rem;font-weight:900;min-width:32px;text-align:center; }
.tm-score-winner { color:var(--success); }
.tm-score-loser { color:var(--text-muted); }
.tm-vs { font-family:var(--font-heading);font-weight:800;font-size:0.9rem;color:var(--text-muted);min-width:60px;text-align:center; }
.tm-winner-tag { font-size:0.72rem;color:var(--success);font-weight:700;white-space:nowrap; }

/* Panels (schedule/results) */
.tm-panel { padding:16px 20px;background:rgba(0,0,0,0.15);border-top:1px solid rgba(255,255,255,0.04); }
.tm-schedule-form { display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap; }
.tm-panel-title { font-family:var(--font-heading);color:var(--accent-main);text-transform:uppercase;font-size:0.95rem;margin-bottom:16px; }
.tm-panel-subtitle { font-size:0.85rem;color:var(--text-secondary);margin:20px 0 10px; }

/* Score Section */
.tm-score-section { display:flex;gap:12px;align-items:stretch; }
.tm-score-team { flex:1;background:rgba(255,255,255,0.02);padding:14px;border-radius:10px;border:1px solid rgba(255,255,255,0.04); }
.tm-score-team-blue { border-left:3px solid #3b82f6; }
.tm-score-team-red { border-left:3px solid #ef4444; }
.tm-score-team h5 { font-size:0.85rem;margin-bottom:10px; }
.tm-score-inputs { display:flex;gap:8px; }
.tm-input-group { flex:1; }
.tm-input-group label { display:block;font-size:0.68rem;color:var(--text-muted);margin-bottom:3px;text-transform:uppercase; }
.tm-score-vs { display:flex;align-items:center;font-family:var(--font-heading);font-size:1.2rem;color:var(--text-muted);padding:0 4px; }

/* Winner Section */
.tm-winner-section { margin-top:14px; }
.tm-winner-section .form-label { font-size:0.9rem;font-weight:700; }

/* Player Stats */
.tm-stats-grid { display:flex;gap:16px; }
.tm-stats-col { flex:1; }
.tm-stats-team-header { text-transform:uppercase;letter-spacing:1px;font-size:0.72rem;margin-bottom:8px;padding-bottom:4px;border-bottom:1px solid rgba(255,255,255,0.06); }
.tm-stats-team-header.tm-blue { color:#3b82f6; }
.tm-stats-team-header.tm-red { color:#ef4444; }

.tm-player-row { display:flex;align-items:center;gap:5px;margin-bottom:6px; }
.tm-player-name { width:90px;font-size:0.76rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-secondary); }
.tm-hero-btn { display:flex;align-items:center;gap:4px;width:90px;padding:3px 6px;border-radius:6px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.03);cursor:pointer;color:var(--text-muted);font-size:0.72rem;transition:all 0.2s;overflow:hidden;height:30px; }
.tm-hero-btn:hover { border-color:rgba(124,92,252,0.4);background:rgba(124,92,252,0.08); }
.tm-hero-btn img { width:22px;height:22px;border-radius:4px;object-fit:cover; }
.tm-hero-btn span { white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.tm-kda-input { width:46px;height:30px;padding:4px 6px;font-size:0.78rem;text-align:center;background:var(--bg-card);border:1px solid rgba(255,255,255,0.08);border-radius:6px;color:var(--text-primary); }
.tm-kda-input:focus { border-color:var(--accent-main);outline:none; }

.tm-form-footer { display:flex;justify-content:flex-end;gap:8px;margin-top:16px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.06); }

/* Glass button */
.btn-glass { background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:var(--text-secondary); }
.btn-glass:hover { background:rgba(255,255,255,0.1); }

/* ===== HERO PICKER MODAL ===== */
.hero-modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:9999;justify-content:center;align-items:center;padding:20px;backdrop-filter:blur(4px); }
.hero-modal-overlay.active { display:flex; }
.hero-modal { background:var(--bg-card,#141822);border-radius:16px;width:100%;max-width:720px;max-height:85vh;overflow:hidden;border:1px solid rgba(255,255,255,0.08);display:flex;flex-direction:column;box-shadow:0 24px 80px rgba(0,0,0,0.7); }
.hero-modal-header { display:flex;justify-content:space-between;align-items:center;padding:18px 22px;border-bottom:1px solid rgba(255,255,255,0.06); }
.hero-modal-header h3 { font-family:var(--font-heading);font-weight:800;font-size:1.15rem;margin:0;text-transform:uppercase;letter-spacing:0.5px; }
.hero-modal-close { background:none;border:none;color:var(--text-muted);font-size:1.8rem;cursor:pointer;width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all 0.2s; }
.hero-modal-close:hover { background:rgba(255,255,255,0.08);color:#fff; }
.hero-modal-search { padding:12px 22px 8px; }
.hero-modal-tabs { display:flex;gap:4px;padding:6px 22px 12px;overflow-x:auto;flex-shrink:0; }
.hero-tab { padding:6px 12px;border-radius:8px;border:1px solid rgba(255,255,255,0.06);background:transparent;color:var(--text-muted);font-size:0.75rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:all 0.2s; }
.hero-tab:hover,.hero-tab.active { background:rgba(124,92,252,0.15);border-color:rgba(124,92,252,0.4);color:#fff; }
.hero-modal-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:8px;padding:10px 22px 22px;overflow-y:auto;flex:1; }
.hero-pick-card { display:flex;flex-direction:column;align-items:center;gap:4px;padding:8px;border-radius:10px;border:2px solid transparent;cursor:pointer;transition:all 0.2s;text-align:center;background:rgba(255,255,255,0.02); }
.hero-pick-card:hover { border-color:rgba(124,92,252,0.5);background:rgba(124,92,252,0.1);transform:scale(1.06); }
.hero-pick-card.selected { border-color:var(--accent,#7c5cfc);background:rgba(124,92,252,0.2);box-shadow:0 0 16px rgba(124,92,252,0.3); }
.hero-pick-card img { width:52px;height:52px;border-radius:8px;object-fit:cover; }
.hero-pick-card span { font-size:0.65rem;font-weight:600;color:var(--text-secondary);line-height:1.2; }

@keyframes pulse-border { 0%,100%{border-left-color:var(--danger);} 50%{border-left-color:transparent;} }

/* Responsive */
@media(max-width:768px) {
    .tm-matchup { flex-direction:column;gap:8px; }
    .tm-team { justify-content:center !important; }
    .tm-score-section { flex-direction:column; }
    .tm-stats-grid { flex-direction:column; }
    .tm-stats-row { flex-direction:column; }
    .tm-stat-card { flex:unset; }
}
</style>

<script>
let currentHeroTargetId = null;

function togglePanel(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function openAdminHeroPicker(targetId) {
    currentHeroTargetId = targetId;
    document.getElementById('heroModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('heroSearch').focus(), 100);
}

function closeHeroPicker() {
    document.getElementById('heroModal').classList.remove('active');
    document.body.style.overflow = '';
    currentHeroTargetId = null;
}

function pickHero(name, imgSrc) {
    if (currentHeroTargetId) {
        document.getElementById('heroInput_' + currentHeroTargetId).value = name;
        const img = document.getElementById('heroImg_' + currentHeroTargetId);
        img.src = imgSrc;
        img.style.display = 'block';
        document.getElementById('heroText_' + currentHeroTargetId).textContent = name;
    }
    document.querySelectorAll('.hero-pick-card').forEach(c => c.classList.remove('selected'));
    event.target.closest('.hero-pick-card')?.classList.add('selected');
    closeHeroPicker();
}

function filterHeroes() {
    const q = document.getElementById('heroSearch').value.toLowerCase();
    document.querySelectorAll('.hero-pick-card').forEach(c => {
        c.style.display = c.dataset.name.includes(q) ? '' : 'none';
    });
}

function filterByRole(role, btn) {
    document.querySelectorAll('.hero-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.hero-pick-card').forEach(c => {
        c.style.display = (role === 'all' || c.dataset.role === role) ? '' : 'none';
    });
    document.getElementById('heroSearch').value = '';
}

function calcMatchStats(matchId) {
    let t1Kills = 0, t2Kills = 0;
    document.querySelectorAll('.k-input-t1-' + matchId).forEach(i => { t1Kills += parseInt(i.value) || 0; });
    document.querySelectorAll('.k-input-t2-' + matchId).forEach(i => { t2Kills += parseInt(i.value) || 0; });
    const t1El = document.getElementById('t1_kills_' + matchId);
    const t2El = document.getElementById('t2_kills_' + matchId);
    if (t1El) t1El.value = t1Kills;
    if (t2El) t2El.value = t2Kills;
}

function togglePlayerStats(checkbox, playerInputId, matchId, teamPrefix) {
    const inputsContainer = document.getElementById('inputs_' + playerInputId);
    const allInputs = inputsContainer.querySelectorAll('input');
    const checkedCount = document.querySelectorAll(`.${teamPrefix}-chk-${matchId}:checked`).length;
    const countBadge = document.getElementById(`${teamPrefix}_count_${matchId}`);
    
    // Prevent selecting more than 5
    if (checkbox.checked && checkedCount > 5) {
        checkbox.checked = false;
        alert('Solo puedes seleccionar un máximo de 5 jugadores por equipo.');
        return;
    }

    countBadge.textContent = `${checkedCount}/5 Seleccionados`;
    countBadge.className = `badge ${checkedCount === 5 ? 'badge-success' : 'badge-purple'}`;

    if (checkbox.checked) {
        inputsContainer.style.display = 'flex';
        allInputs.forEach(input => input.disabled = false);
    } else {
        inputsContainer.style.display = 'none';
        allInputs.forEach(input => input.disabled = true);
        // Clear values when hiding
        allInputs.forEach(input => {
            if (input.type === 'number') input.value = '';
        });
        document.getElementById('heroInput_' + playerInputId).value = '';
        document.getElementById('heroImg_' + playerInputId).style.display = 'none';
        document.getElementById('heroText_' + playerInputId).textContent = '+ Héroe';
        calcMatchStats(matchId);
    }
}

function validateMatchSubmission(matchId) {
    const team1Count = document.querySelectorAll(`.t1-chk-${matchId}:checked`).length;
    const team2Count = document.querySelectorAll(`.t2-chk-${matchId}:checked`).length;

    // We allow submission if a team has NO players checked (e.g. Win by Default/Forfeit) 
    // BUT if they selected players, it MUST BE exactly 5 (or 0)
    // Actually, League usually requires explicitly 5 players or 0 players for forfeit.
    
    if ((team1Count !== 5 && team1Count !== 0) || (team2Count !== 5 && team2Count !== 0)) {
        alert(`Debes seleccionar exactamente 5 titulares por equipo (o 0 si es victoria por incomparecencia/forfeit).\n\nSeleccionados Equipo 1: ${team1Count}\nSeleccionados Equipo 2: ${team2Count}`);
        return false;
    }

    // Verify heroes are picked
    let missingHero = false;
    document.querySelectorAll(`#result-${matchId} input[name$="[hero]"]`).forEach(input => {
        if (!input.disabled && !input.value) {
            missingHero = true;
        }
    });

    if (missingHero) {
        alert('Debes seleccionar el héroe utilizado para todos los jugadores marcados como titulares.');
        return false;
    }

    return confirm('¿Confirmar resultados? El bracket se actualizará automáticamente.');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
