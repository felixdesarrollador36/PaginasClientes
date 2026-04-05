<?php
$page = 'tournaments';
$pageCss = 'tournament-view';
$tournId = $_GET['id'] ?? 0;
$tournCtrl = new TournamentController();
$tournament = $tournCtrl->getTournament($tournId);
if (!$tournament) { setFlash('error', 'Torneo no encontrado.'); redirect('tournaments'); }
$registeredTeams = $tournCtrl->getRegisteredTeams($tournId);
$matches = $tournCtrl->getMatches($tournId);
$pageTitle = $tournament['name'];
$bracketFileSlug = preg_replace('/[^a-zA-Z0-9-]/', '-', strtolower($tournament['name']));

// Get group standings for group_stage format
$groupStandings = [];
$isGroupStage = ($tournament['format'] === 'group_stage');
if ($isGroupStage) {
    $groupStandings = $tournCtrl->getGroupStandings($tournId);
}

// Separate group matches and knockout matches
$groupMatches = [];
$knockoutMatches = [];
foreach ($matches as $m) {
    if ($m['bracket_type'] === 'group') {
        $groupMatches[] = $m;
    } else {
        $knockoutMatches[] = $m;
    }
}

$teamCtrl = new TeamController();
$myTeam = $teamCtrl->getUserTeam(currentUserId());
$myTeamMembers = [];
$isRegistered = false;
if ($myTeam) {
    $myTeamMembers = $teamCtrl->getTeamMembers($myTeam['team_id']);
    foreach ($registeredTeams as $rt) {
        if ($rt['team_id'] == $myTeam['team_id']) { $isRegistered = true; break; }
    }
}

$minRosterSize = max(5, (int)($tournament['team_size'] ?? 5));
$substituteSlots = max(0, (int)($tournament['substitutes'] ?? 0));
$requiredRosterSize = $minRosterSize + $substituteSlots;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content tournament-view-page" data-bracket-file-slug="<?= htmlspecialchars($bracketFileSlug, ENT_QUOTES) ?>">
    <a href="<?= url('tournaments') ?>" class="tv-back-link">
        <svg class="icon-svg sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Torneos
    </a>

    <!-- Tournament Header -->
    <div class="card mb-3">
        <div class="tv-header-row">
            <div>
                <div class="tv-header-badges">
                    <span class="tournament-status <?= $tournament['status'] ?>">
                        <?php
                        $statusLabels = ['draft' => 'Borrador', 'registration' => 'Inscripciones Abiertas', 'ready' => 'Listo para Iniciar', 'in_progress' => 'En Curso', 'completed' => 'Finalizado'];
                        echo $statusLabels[$tournament['status']] ?? $tournament['status'];
                        ?>
                    </span>
                    <?php if (!empty($tournament['is_main_tournament'])): ?>
                    <span class="badge badge-yellow tv-main-badge">⭐ Torneo Principal</span>
                    <?php endif; ?>
                </div>
                <h1 class="page-title tv-page-title"><?= htmlspecialchars($tournament['name']) ?></h1>
            </div>
            <?php if (in_array($tournament['status'], ['registration', 'ready']) && $myTeam && $myTeam['is_captain'] && !$isRegistered): ?>
                <button type="button" class="btn btn-primary btn-lg" data-open-roster-modal>Inscribir Equipo (<?= $requiredRosterSize ?> Jugadores)</button>
            <?php elseif ($isRegistered): ?>
                <div class="tv-header-actions">
                    <span class="badge badge-green tv-registered-badge">Tu equipo está inscrito</span>
                    <?php if (in_array($tournament['status'], ['registration', 'ready']) && $myTeam && $myTeam['is_captain']): ?>
                    <form method="POST" action="<?= url('tournaments/leave/' . $tournId) ?>" onsubmit="return confirm('¿Seguro que deseas retirar tu equipo de este torneo?');">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn-danger btn-sm">Salir del Torneo</button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="tv-stat-grid">
            <div class="stat-card tv-stat-card">
                <div class="stat-icon purple">
                    <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                </div>
                <div><div class="stat-value tv-stat-value-base"><?= TOURNAMENT_FORMATS[$tournament['format']] ?? '' ?></div><div class="stat-label">Formato</div></div>
            </div>
            <div class="stat-card tv-stat-card">
                <div class="stat-icon green">
                    <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                </div>
                <div><div class="stat-value tv-stat-value-large"><?= $tournament['registered_teams'] ?>/<?= $tournament['max_teams'] ?></div><div class="stat-label">Equipos</div></div>
            </div>
            <div class="stat-card tv-stat-card">
                <div class="stat-icon blue">
                    <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 17.5L3 6V3h3l11.5 11.5"/><path d="M13 19l6-6"/><path d="M16 16l4 4"/></svg>
                </div>
                <div>
                    <div class="stat-value tv-stat-value-base">
                        <?php if ($substituteSlots > 0): ?>
                            5 titulares + <?= $substituteSlots ?> suplente<?= $substituteSlots > 1 ? 's' : '' ?>
                        <?php else: ?>
                            5 titulares
                        <?php endif; ?>
                    </div>
                    <div class="stat-label">Roster permitido</div>
                </div>
            </div>
            <?php if ($tournament['prize_pool']): ?>
            <div class="stat-card tv-stat-card">
                <div class="stat-icon orange">
                    <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>
                </div>
                <div><div class="stat-value tv-stat-value-base"><?= htmlspecialchars($tournament['prize_pool']) ?></div><div class="stat-label">Premios</div></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tabs" id="tournament-tabs">
        <button type="button" class="tab active" data-tournament-tab="info">Información</button>
        <button type="button" class="tab" data-tournament-tab="teams">Equipos</button>
        <?php if ($isGroupStage && !empty($groupStandings)): ?>
        <button type="button" class="tab" data-tournament-tab="groups">Grupos</button>
        <?php endif; ?>
        <button type="button" class="tab" data-tournament-tab="bracket">
            <?= $isGroupStage ? 'Bracket / Llaves' : 'Bracket' ?>
        </button>
        <?php if (!empty($tournament['prize_pool'])): ?>
        <button type="button" class="tab" data-tournament-tab="prizes">Premios</button>
        <?php endif; ?>
        <?php if ($tournament['stream_url']): ?>
        <button type="button" class="tab" data-tournament-tab="stream">Stream</button>
        <?php endif; ?>
    </div>

    <!-- Info Tab -->
    <div class="tab-content active" id="tab-info">
        <div class="grid grid-2">
            <div class="card">
                <h3 class="card-title tv-card-title-spaced">Detalles</h3>
                <?php if ($tournament['description']): ?>
                <div class="tv-copy tv-copy--lead"><?= nl2br(htmlspecialchars($tournament['description'])) ?></div>
                <?php endif; ?>
                <div class="tv-meta-list">
                    <?php if ($tournament['start_date']): ?><div><strong>Inicio:</strong> <?= date('d M Y, H:i', strtotime($tournament['start_date'])) ?></div><?php endif; ?>
                    <?php if ($tournament['end_date']): ?><div><strong>Fin:</strong> <?= date('d M Y, H:i', strtotime($tournament['end_date'])) ?></div><?php endif; ?>
                    <?php if ($tournament['entry_fee'] > 0): ?><div><strong>Inscripción:</strong> $<?= number_format($tournament['entry_fee'], 2) ?></div><?php endif; ?>
                    <?php if ($tournament['min_rank']): ?><div><strong>Rango Mínimo:</strong> <?= ML_RANKS[$tournament['min_rank']] ?? $tournament['min_rank'] ?></div><?php endif; ?>
                    <?php if ($tournament['season_name']): ?><div><strong>Temporada:</strong> <?= htmlspecialchars($tournament['season_name']) ?></div><?php endif; ?>
                </div>
            </div>
            <?php if ($tournament['rules']): ?>
            <div class="card">
                <h3 class="card-title tv-card-title-spaced">Reglas</h3>
                <div class="tv-copy tv-copy--rules"><?= nl2br(htmlspecialchars($tournament['rules'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Prizes Tab -->
    <?php if (!empty($tournament['prize_pool'])): ?>
    <div class="tab-content" id="tab-prizes">
        <div class="card">
            <h3 class="card-title tv-prize-title">
                <span class="stat-icon orange tv-prize-icon"><svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg></span>
                Distribución de Premios
            </h3>
            <div class="tv-prize-copy">
                <?= nl2br(htmlspecialchars($tournament['prize_pool'])) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Teams Tab -->
    <div class="tab-content" id="tab-teams">
        <div class="card">
            <h3 class="card-title mb-2">Equipos Inscritos (<?= count($registeredTeams) ?>)</h3>
            <?php if (!empty($registeredTeams)): ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>#</th><th>Equipo</th><th>Grupo</th><th>Capitán</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($registeredTeams as $i => $rt): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="tv-team-cell">
                            <?php if ($rt['team_logo']): ?><img src="<?= UPLOAD_URL . $rt['team_logo'] ?>" class="tv-team-logo"><?php endif; ?>
                            <a href="<?= url('teams/view/' . $rt['team_id']) ?>" class="tv-team-link"><?= htmlspecialchars($rt['team_name']) ?></a>
                            <?php if ($rt['team_tag']): ?><span class="badge badge-purple"><?= $rt['team_tag'] ?></span><?php endif; ?>
                        </td>
                        <td><?= $rt['group_name'] ? '<span class="badge badge-blue">Grupo ' . $rt['group_name'] . '</span>' : '-' ?></td>
                        <td><?= htmlspecialchars($rt['captain_name']) ?></td>
                        <td><span class="badge badge-<?= $rt['status'] === 'registered' ? 'green' : 'yellow' ?>"><?= ucfirst($rt['status']) ?></span></td>
                    </tr>
                    <?php if ((isAdmin() || isSuperAdmin() || isModerator()) && !empty($rt['roster_data'])): ?>
                    <tr class="tv-roster-row">
                        <td colspan="5" class="tv-roster-cell">
                            <strong class="tv-roster-title">
                                <svg class="icon-svg tv-roster-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> 
                                Roster Detallado (Solo visible para Moderadores y Admins)
                            </strong>
                            <div class="tv-roster-grid">
                                <?php 
                                $mainCount = 0;
                                $subCount = 0;
                                foreach ($rt['roster_data'] as $p): 
                                    $isSub = !empty($p['is_substitute']);
                                    if (!$isSub) $mainCount++;
                                    else $subCount++;
                                ?>
                                <div class="tv-roster-member-card <?= $isSub ? 'tv-roster-sub' : 'tv-roster-main' ?>">
                                    <?php if ($isSub): ?>
                                    <span class="tv-roster-badge tv-roster-badge--sub">Suplente</span>
                                    <?php else: ?>
                                    <span class="tv-roster-badge tv-roster-badge--main">Titular</span>
                                    <?php endif; ?>
                                    <div class="tv-roster-member-head">
                                        <?= htmlspecialchars($p['username']) ?>
                                        <span class="tv-roster-member-ign">IGN: <?= htmlspecialchars($p['ml_nickname'] ?? 'N/A') ?></span>
                                    </div>
                                    <?php if ($p['ml_id']): ?><div class="tv-roster-contact tv-roster-contact--ml"><strong>ML ID:</strong> <?= htmlspecialchars($p['ml_id']) ?> | <strong>Server:</strong> <?= htmlspecialchars($p['ml_server'] ?? 'N/A') ?></div><?php endif; ?>
                                    <?php if ($p['whatsapp']): ?><div class="tv-roster-contact tv-roster-contact--whatsapp"><strong>WA:</strong> <?= htmlspecialchars($p['whatsapp']) ?></div><?php endif; ?>
                                    <?php if ($p['discord']): ?><div class="tv-roster-contact tv-roster-contact--discord"><strong>DS:</strong> <?= htmlspecialchars($p['discord']) ?></div><?php endif; ?>
                                    <?php if ($p['phone_brand']): ?><div class="tv-roster-contact tv-roster-contact--phone"><strong>Cel:</strong> <?= htmlspecialchars($p['phone_brand']) ?></div><?php endif; ?>
                                    <?php if ($p['email']): ?><div class="tv-roster-contact tv-roster-contact--muted"><strong>Email:</strong> <?= htmlspecialchars($p['email']) ?></div><?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($mainCount > 0 || $subCount > 0): ?>
                            <div class="tv-roster-summary">
                                <span class="tv-roster-summary-main"><strong><?= $mainCount ?></strong> Titular<?= $mainCount != 1 ? 'es' : '' ?></span>
                                <?php if ($subCount > 0): ?>
                                | <span class="tv-roster-summary-sub"><strong><?= $subCount ?></strong> Suplente<?= $subCount != 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state tv-empty-state-compact">
                <p>No hay equipos inscritos aún</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Groups Tab (only for group_stage) -->
    <?php if ($isGroupStage && !empty($groupStandings)): ?>
    <div class="tab-content" id="tab-groups">
        <div class="grid grid-2">
            <?php foreach ($groupStandings as $groupName => $teams): ?>
            <div class="card">
                <h3 class="card-title tv-group-title">
                    <span class="badge badge-purple tv-group-badge">Grupo <?= htmlspecialchars($groupName) ?></span>
                </h3>
                <div class="table-wrapper">
                    <table class="table tv-group-table">
                        <thead>
                            <tr>
                                <th class="tv-group-col-rank">#</th>
                                <th>Equipo</th>
                                <th class="tv-text-center">PJ</th>
                                <th class="tv-text-center">V</th>
                                <th class="tv-text-center">D</th>
                                <th class="tv-text-center">Diff</th>
                                <th class="tv-text-center"><strong>Pts</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($teams as $pos => $team): ?>
                            <tr class="<?= $pos < 2 ? 'tv-group-row-qualified' : '' ?>">
                                <td>
                                    <?php if ($pos < 2): ?>
                                        <span class="tv-group-rank-qualified"><?= $pos + 1 ?></span>
                                    <?php else: ?>
                                        <span class="tv-group-rank-muted"><?= $pos + 1 ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="tv-group-team-cell">
                                    <?php if ($team['logo']): ?>
                                    <img src="<?= UPLOAD_URL . $team['logo'] ?>" class="tv-group-team-logo">
                                    <?php endif; ?>
                                    <span class="tv-group-team-name"><?= htmlspecialchars($team['name']) ?></span>
                                    <?php if ($team['tag']): ?><span class="badge badge-purple tv-group-team-tag"><?= $team['tag'] ?></span><?php endif; ?>
                                </td>
                                <td class="tv-text-center"><?= $team['matches_played'] ?? 0 ?></td>
                                <td class="tv-text-center tv-text-success"><?= $team['wins'] ?? 0 ?></td>
                                <td class="tv-text-center tv-text-danger"><?= $team['losses'] ?? 0 ?></td>
                                <td class="tv-text-center">
                                    <?php $kd = $team['kill_diff'] ?? 0; ?>
                                    <span class="<?= $kd > 0 ? 'tv-kd-positive' : ($kd < 0 ? 'tv-kd-negative' : 'tv-kd-neutral') ?>">
                                        <?= $kd > 0 ? '+' . $kd : $kd ?>
                                    </span>
                                </td>
                                <td class="tv-text-center">
                                    <strong class="tv-group-points"><?= $team['points'] ?? 0 ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="tv-group-note">
                    Top 2 clasifican a llaves ✓
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bracket Tab -->
    <div class="tab-content" id="tab-bracket">
        <div class="card" id="bracket-card-wrapper">
            <div class="tv-bracket-toolbar">
                <h3 class="card-title tv-bracket-title"><?= $isGroupStage ? 'Llaves Eliminatorias' : 'Bracket' ?></h3>
                <button type="button" class="btn btn-secondary btn-sm" data-export-bracket>📷 Exportar Imagen</button>
            </div>
            <?php
            // Group matches into Winners and Losers bracket
            $displayMatches = $isGroupStage ? $knockoutMatches : $matches;
            $winnersMatches = [];
            $losersMatches = [];
            
            if (!empty($displayMatches)) {
                foreach ($displayMatches as $m) {
                    if (strpos($m['round'], 'LB') !== false) {
                        // Losers bracket: format is LB1, LB2, etc. (we extract the number)
                        $roundNum = (int) str_replace('LB', '', $m['round']);
                        $losersMatches[$roundNum][] = $m;
                    } else {
                        // Winners bracket
                        $winnersMatches[$m['round']][] = $m;
                    }
                }
                ksort($winnersMatches);
                ksort($losersMatches);
            }
            ?>
            
            <?php if (!empty($displayMatches)): ?>
            
            <div class="tv-bracket-section">
                <h4 class="tv-bracket-heading tv-bracket-heading--winners">Winners Bracket</h4>
                <div class="bracket-container">
                    <?php
                    $totalWRounds = count($winnersMatches);
                    // Render Winners Bracket
                    foreach ($winnersMatches as $roundNum => $roundMatches):
                        $remaining = $totalWRounds - $roundNum + 1;
                        if ($remaining == 1 && empty($losersMatches)) $label = 'Final';
                        elseif ($remaining == 1 && !empty($losersMatches)) $label = 'Grand Final';
                        elseif ($remaining == 2) $label = 'Semifinal';
                        elseif ($remaining == 3) $label = 'Cuartos';
                        elseif ($remaining == 4) $label = 'Octavos';
                        else $label = 'Ronda ' . $roundNum;
                    ?>
                    <div class="bracket-round">
                        <div class="bracket-round-header"><?= $label ?></div>
                        <?php foreach ($roundMatches as $m): ?>
                        <div class="bracket-match">
                            <div class="bracket-team <?= $m['winner_id'] == $m['team1_id'] && $m['winner_id'] ? 'winner' : '' ?>">
                                <span class="team-name" title="<?= !empty($m['team1_name']) ? htmlspecialchars($m['team1_name']) : '' ?>">
                                    <?= !empty($m['team1_name']) ? htmlspecialchars($m['team1_name']) : '' ?>
                                </span>
                                <span class="bracket-score"><?= $m['team1_score'] !== null ? $m['team1_score'] : '-' ?></span>
                            </div>
                            <div class="bracket-team <?= $m['winner_id'] == $m['team2_id'] && $m['winner_id'] ? 'winner' : '' ?>">
                                <span class="team-name" title="<?= !empty($m['team2_name']) ? htmlspecialchars($m['team2_name']) : '' ?>">
                                    <?= !empty($m['team2_name']) ? htmlspecialchars($m['team2_name']) : '' ?>
                                </span>
                                <span class="bracket-score"><?= $m['team2_score'] !== null ? $m['team2_score'] : '-' ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!empty($losersMatches)): ?>
            <div>
                <h4 class="tv-bracket-heading tv-bracket-heading--losers">Losers Bracket</h4>
                <div class="bracket-container">
                    <?php
                    $totalLRounds = count($losersMatches);
                    // Render Losers Bracket
                    foreach ($losersMatches as $roundNum => $roundMatches):
                        $remaining = $totalLRounds - $roundNum + 1;
                        if ($remaining == 1) $label = 'LB Final';
                        elseif ($remaining == 2) $label = 'LB Semifinal';
                        else $label = 'LB Round ' . $roundNum;
                    ?>
                    <div class="bracket-round">
                        <div class="bracket-round-header tv-bracket-round-header--losers"><?= $label ?></div>
                        <?php foreach ($roundMatches as $m): ?>
                        <div class="bracket-match">
                            <div class="bracket-team <?= $m['winner_id'] == $m['team1_id'] && $m['winner_id'] ? 'winner' : '' ?>">
                                <span class="team-name" title="<?= htmlspecialchars($m['team1_name'] ?? 'TBD') ?>"><?= htmlspecialchars($m['team1_name'] ?? 'TBD') ?></span>
                                <span class="bracket-score"><?= $m['team1_score'] !== null ? $m['team1_score'] : '-' ?></span>
                            </div>
                            <div class="bracket-team <?= $m['winner_id'] == $m['team2_id'] && $m['winner_id'] ? 'winner' : '' ?>">
                                <span class="team-name" title="<?= htmlspecialchars($m['team2_name'] ?? 'TBD') ?>"><?= htmlspecialchars($m['team2_name'] ?? 'TBD') ?></span>
                                <span class="bracket-score"><?= $m['team2_score'] !== null ? $m['team2_score'] : '-' ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="empty-state tv-empty-state-compact">
                <div class="empty-state-icon">
                    <svg class="icon-svg lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                </div>
                <p><?= $isGroupStage ? 'Las llaves eliminatorias se generarán al terminar la fase de grupos' : 'El bracket aún no ha sido generado' ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($tournament['stream_url']): ?>
    <div class="tab-content" id="tab-stream">
        <div class="card">
            <div class="tv-stream-header">
                <span class="live-indicator"><span class="live-dot"></span> STREAM</span>
            </div>
            <a href="<?= htmlspecialchars($tournament['stream_url']) ?>" target="_blank" class="btn btn-danger btn-lg btn-block">Ver Transmisión</a>
        </div>
    </div>
    <?php endif; ?>

</div></div>

<?php if (in_array($tournament['status'], ['registration', 'ready']) && $myTeam && $myTeam['is_captain'] && !$isRegistered): ?>
<!-- Roster Selection Modal -->
<div id="rosterModal" class="tournament-roster-modal" data-min-roster="<?= $minRosterSize ?>" data-max-roster="<?= $minRosterSize + $substituteSlots ?>">
    <div class="card tournament-roster-modal-card">
        <button type="button" data-close-roster-modal class="tournament-roster-modal-close">&times;</button>
        <h3 class="tournament-roster-modal-title">Seleccionar Roster</h3>
        
        <?php if ($substituteSlots > 0): ?>
        <p class="tournament-roster-modal-copy">
            Primero selecciona <strong><?= $minRosterSize ?> jugadores principales</strong>, luego optionally agrega <strong><?= $substituteSlots ?> suplente<?= $substituteSlots > 1 ? 's' : '' ?></strong>.
        </p>

        <form id="rosterForm" method="POST" action="<?= url('tournaments/register/' . $tournId) ?>" class="tournament-roster-form">
            <?= csrfField() ?>
            
            <div class="tournament-roster-section">
                <h4 class="tournament-roster-section-title"><?= $minRosterSize ?> Jugadores Principales (Obligatorio)</h4>
                <div class="tournament-roster-list">
                    <?php foreach ($myTeamMembers as $member): ?>
                    <label class="tournament-roster-option">
                        <input type="checkbox" name="main_roster[]" value="<?= $member['user_id'] ?>" class="roster-checkbox main-roster-checkbox" data-main-checkbox>
                        <?php if ($member['avatar']): ?>
                            <img src="<?= UPLOAD_URL . $member['avatar'] ?>" class="tournament-roster-avatar">
                        <?php else: ?>
                            <div class="user-avatar-placeholder tournament-roster-avatar-placeholder"><?= strtoupper(substr($member['username'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <div class="tournament-roster-member-body">
                            <div class="tournament-roster-member-name"><?= htmlspecialchars(!empty($member['ml_nickname']) ? $member['ml_nickname'] : $member['username']) ?></div>
                            <div class="tournament-roster-member-meta">
                                <?= ML_ROLES[$member['role']] ?? $member['role'] ?> • <?= ML_LANES[$member['lane_1']] ?? 'Sin línea' ?>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="tournament-roster-count" style="margin-top:8px;">Principales: <strong id="mainRosterCount">0</strong> / <?= $minRosterSize ?></div>
            </div>

            <div class="tournament-roster-section" style="margin-top:16px;">
                <h4 class="tournament-roster-section-title">Suplentes (Opcional - Máx <?= $substituteSlots ?>)</h4>
                <div class="tournament-roster-list" id="substituteList">
                    <?php foreach ($myTeamMembers as $member): ?>
                    <label class="tournament-roster-option substitute-option" style="display:none;">
                        <input type="checkbox" name="substitute_roster[]" value="<?= $member['user_id'] ?>" class="roster-checkbox substitute-checkbox" data-sub-checkbox disabled>
                        <?php if ($member['avatar']): ?>
                            <img src="<?= UPLOAD_URL . $member['avatar'] ?>" class="tournament-roster-avatar">
                        <?php else: ?>
                            <div class="user-avatar-placeholder tournament-roster-avatar-placeholder"><?= strtoupper(substr($member['username'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <div class="tournament-roster-member-body">
                            <div class="tournament-roster-member-name"><?= htmlspecialchars(!empty($member['ml_nickname']) ? $member['ml_nickname'] : $member['username']) ?></div>
                            <div class="tournament-roster-member-meta">
                                <?= ML_ROLES[$member['role']] ?? $member['role'] ?> • <?= ML_LANES[$member['lane_1']] ?? 'Sin línea' ?>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="tournament-roster-count" style="margin-top:8px;">Suplentes: <strong id="subRosterCount">0</strong> / <?= $substituteSlots ?></div>
            </div>
        </form>

        <div class="tournament-roster-footer">
            <button type="button" class="btn btn-primary" id="btnSubmitRoster" data-submit-roster disabled>Confirmar Inscripción (Mínimo <?= $minRosterSize ?> principales)</button>
        </div>
        <?php else: ?>
        
        <p class="tournament-roster-modal-copy">
            Selecciona <strong><?= $minRosterSize ?> jugadores</strong> para este torneo.
        </p>

        <form id="rosterForm" method="POST" action="<?= url('tournaments/register/' . $tournId) ?>" class="tournament-roster-form">
            <?= csrfField() ?>
            <div class="tournament-roster-list">
                <?php foreach ($myTeamMembers as $member): ?>
                <label class="tournament-roster-option">
                    <input type="checkbox" name="roster[]" value="<?= $member['user_id'] ?>" class="roster-checkbox" data-roster-checkbox>
                    <?php if ($member['avatar']): ?>
                        <img src="<?= UPLOAD_URL . $member['avatar'] ?>" class="tournament-roster-avatar">
                    <?php else: ?>
                        <div class="user-avatar-placeholder tournament-roster-avatar-placeholder"><?= strtoupper(substr($member['username'], 0, 1)) ?></div>
                    <?php endif; ?>
                    <div class="tournament-roster-member-body">
                        <div class="tournament-roster-member-name"><?= htmlspecialchars(!empty($member['ml_nickname']) ? $member['ml_nickname'] : $member['username']) ?></div>
                        <div class="tournament-roster-member-meta">
                            <?= ML_ROLES[$member['role']] ?? $member['role'] ?> • <?= ML_LANES[$member['lane_1']] ?? 'Sin línea' ?>
                        </div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </form>

        <div class="tournament-roster-footer">
            <div class="tournament-roster-count">
                Seleccionados: <strong id="rosterCount">0</strong> / <?= $minRosterSize ?>
            </div>
            <button type="button" class="btn btn-primary" id="btnSubmitRoster" data-submit-roster disabled>Confirmar Inscripción</button>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
