<?php
$pageTitle = 'Rankings';
$page = 'rankings';
$pageCss = 'rankings';
$rankCtrl = new RankingController();
$seasons = $rankCtrl->getSeasons();
$seasonId = isset($_GET['season']) && $_GET['season'] !== '' ? intval($_GET['season']) : null;
$tournaments = $rankCtrl->getActiveTournaments($seasonId);
$tournamentId = isset($_GET['tournament']) && $_GET['tournament'] !== '' ? intval($_GET['tournament']) : null;

// Fetch dynamic rankings
$teamRankings = $rankCtrl->getDynamicTeamRankings($seasonId, $tournamentId);
$playerRankings = $rankCtrl->getDynamicPlayerRankings($seasonId, $tournamentId);
$heroRankings = $rankCtrl->getHeroRankings($seasonId, $tournamentId);
$mvpLeaderboard = $rankCtrl->getMvpLeaderboard($seasonId, $tournamentId);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content rankings-page" data-rankings-base-url="<?= htmlspecialchars(url('rankings'), ENT_QUOTES) ?>">
    <div class="page-header">
        <h1 class="page-title">Rankings</h1>
        <p class="page-subtitle">Clasificación en tiempo real de equipos y jugadores</p>
        <?php if (!$tournamentId): ?>
        <div class="rankings-note rankings-note--main">
            <span>⭐</span> Solo torneos principales (LIGA WOC) cuentan para el ranking global
            <?php if ($tournamentId === null && $seasonId === null): ?>
            <span class="rankings-note-muted">• Filtra por torneo para ver rankings específicos</span>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="rankings-note rankings-note--secondary">
            📊 Mostrando ranking del torneo seleccionado
        </div>
        <?php endif; ?>
    </div>

    <div class="filter-bar rankings-filter-bar">
        <?php if (count($seasons) > 0): ?>
        <select class="form-control rankings-filter rankings-filter--season" id="filterSeason" data-ranking-filter>
            <option value="">-- All Time --</option>
            <?php foreach ($seasons as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $seasonId !== null && $seasonId == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        
        <?php if (count($tournaments) > 0): ?>
        <select class="form-control rankings-filter rankings-filter--tournament" id="filterTournament" data-ranking-filter>
            <option value="">-- Todos los Torneos Principales --</option>
            <?php foreach ($tournaments as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $tournamentId == $t['id'] ? 'selected' : '' ?>><?= !empty($t['is_main_tournament']) ? '⭐ ' : '' ?><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>

    <div class="tabs" id="ranking-tabs">
        <button type="button" class="tab active" data-rank-tab="teams">Equipos</button>
        <button type="button" class="tab" data-rank-tab="players">Jugadores</button>
        <button type="button" class="tab" data-rank-tab="heroes">Héroes</button>
        <button type="button" class="tab" data-rank-tab="mvps">🏅 MVPs</button>
    </div>

    <!-- Team Rankings -->
    <div class="tab-content active" id="tab-teams">
        <div class="card">
            <?php if (!empty($teamRankings)): ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th class="rankings-col-rank">#</th><th>Equipo</th><th>V</th><th>D</th><th>Partidas</th><th>Diff</th><th>Puntos</th></tr></thead>
                    <tbody>
                    <?php foreach ($teamRankings as $i => $tr): $pos = $i + 1; ?>
                    <tr class="<?= $pos <= 3 ? 'rank-' . $pos : '' ?>">
                        <td>
                            <?php if ($pos <= 3): ?>
                                <span class="rank-medal"><?= $pos ?></span>
                            <?php else: ?>
                                <span class="rank-position"><?= $pos ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= url('teams/view/' . ($tr['team_id'] ?? $tr['id'])) ?>" class="rankings-team-link">
                                <?php if ($tr['team_logo']): ?>
                                <img src="<?= UPLOAD_URL . $tr['team_logo'] ?>" class="rankings-team-logo">
                                <?php endif; ?>
                                <?= htmlspecialchars($tr['team_name']) ?>
                                <?php if ($tr['team_tag']): ?><span class="badge badge-purple rankings-team-tag"><?= $tr['team_tag'] ?></span><?php endif; ?>
                            </a>
                        </td>
                        <td class="text-success fw-bold"><?= $tr['wins'] ?></td>
                        <td class="text-danger"><?= $tr['losses'] ?></td>
                        <td><?= $tr['matches_played'] ?></td>
                        <?php $diff = ($tr['kills_diff'] ?? 0) + ($tr['towers_diff'] ?? 0); ?>
                        <td><strong class="<?= $diff > 0 ? 'rankings-diff-positive' : ($diff < 0 ? 'rankings-diff-negative' : 'rankings-diff-neutral') ?>"><?= $diff > 0 ? '+' . $diff : $diff ?></strong></td>
                        <td><strong class="text-accent rankings-points"><?= $tr['points'] ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state rankings-empty-state">
                <div class="empty-state-icon">
                    <svg class="icon-svg lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
                </div>
                <h3 class="empty-state-title">Sin datos de ranking aún</h3>
                <p>Los rankings se actualizarán cuando comiencen los torneos</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Player Rankings -->
    <div class="tab-content" id="tab-players">
        <div class="card">
            <?php if (!empty($playerRankings)): ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th class="rankings-col-rank">#</th><th>Jugador</th><th>Equipo</th><th>KDA Prom.</th><th>Win Rate</th><th>Partidas</th></tr></thead>
                    <tbody>
                    <?php foreach ($playerRankings as $i => $pr): $pos = $i + 1; 
                        $kda = round(($pr['total_kills'] + $pr['total_assists']) / max(1, $pr['total_deaths']), 2);
                        $wr = $pr['matches_played'] > 0 ? round(($pr['matches_won'] / $pr['matches_played']) * 100, 1) : 0;
                    ?>
                    <tr class="<?= $pos <= 3 ? 'rank-' . $pos : '' ?>">
                        <td>
                            <?php if ($pos <= 3): ?>
                                <span class="rank-medal"><?= $pos ?></span>
                            <?php else: ?>
                                <span class="rank-position"><?= $pos ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="rankings-player-cell">
                            <?php if ($pr['avatar']): ?><img src="<?= UPLOAD_URL . $pr['avatar'] ?>" class="rankings-player-avatar"><?php endif; ?>
                            <span class="rankings-player-name"><?= htmlspecialchars($pr['ml_nickname'] ?? $pr['username']) ?></span>
                        </td>
                        <td><?= $pr['team_name'] ? '<span class="badge badge-purple">' . htmlspecialchars($pr['team_tag'] ?? $pr['team_name']) . '</span>' : '-' ?></td>
                        <td><strong class="rankings-kda"><?= $kda ?></strong> 
                            <span class="rankings-kda-meta">( <?= $pr['total_kills'] ?> / <?= $pr['total_deaths'] ?> / <?= $pr['total_assists'] ?> )</span>
                        </td>
                        <td><strong class="<?= $wr >= 50 ? 'rankings-winrate-positive' : 'rankings-winrate-negative' ?>"><?= $wr ?>%</strong></td>
                        <td><?= $pr['matches_played'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state rankings-empty-state">
                <div class="empty-state-icon">
                    <svg class="icon-svg lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <h3 class="empty-state-title">Sin datos de ranking aún</h3>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hero Rankings -->
    <div class="tab-content" id="tab-heroes">
        <div class="card">
            <?php if (!empty($heroRankings)): ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th class="rankings-col-rank">#</th><th>Héroe</th><th>Win Rate</th><th>Picks (Partidas)</th><th>KDA Prom.</th></tr></thead>
                    <tbody>
                    <?php foreach ($heroRankings as $i => $hr): $pos = $i + 1; 
                        $kda = round(($hr['total_kills'] + $hr['total_assists']) / max(1, $hr['total_deaths']), 2);
                        $wr = $hr['win_rate'];
                        
                        // Encontrar la imagen correcta en FOLDERS
                        $heroImgUrl = '';
                        $heroNameRaw = strtolower(trim($hr['hero_name']));
                        foreach (ML_ROLE_FOLDERS as $roleKey => $folderName) {
                            $dir = __DIR__ . "/../assets/heroes_img/{$folderName}/";
                            if (is_dir($dir)) {
                                $files = array_diff(scandir($dir), ['..', '.']);
                                foreach($files as $f) {
                                    if(strtolower(pathinfo($f, PATHINFO_FILENAME)) == $heroNameRaw) {
                                        $heroImgUrl = url("assets/heroes_img/{$folderName}/{$f}");
                                        break 2;
                                    }
                                }
                            }
                        }
                    ?>
                    <tr class="<?= $pos <= 3 ? 'rank-' . $pos : '' ?>">
                        <td>
                            <?php if ($pos <= 3): ?>
                                <span class="rank-medal"><?= $pos ?></span>
                            <?php else: ?>
                                <span class="rank-position"><?= $pos ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="rankings-hero-cell">
                            <?php if ($heroImgUrl): ?>
                                <img src="<?= $heroImgUrl ?>" class="rankings-hero-image">
                            <?php else: ?>
                                <div class="rankings-hero-placeholder">N/A</div>
                            <?php endif; ?>
                            <span class="rankings-hero-name"><?= htmlspecialchars($hr['hero_name']) ?></span>
                        </td>
                        <td><strong class="<?= $wr >= 50 ? 'rankings-winrate-positive rankings-hero-winrate' : 'rankings-winrate-negative rankings-hero-winrate' ?>"><?= $wr ?>%</strong></td>
                        <td><span class="badge badge-purple rankings-picks-badge"><?= $hr['matches_played'] ?></span></td>
                        <td><strong class="rankings-kda"><?= $kda ?></strong> 
                            <span class="rankings-kda-breakdown"><br>( <?= $hr['total_kills'] ?> / <?= $hr['total_deaths'] ?> / <?= $hr['total_assists'] ?> ) K/D/A</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state rankings-empty-state">
                <div class="empty-state-icon">
                    <svg class="icon-svg lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                </div>
                <h3 class="empty-state-title">Aún no hay héroes jugados</h3>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- MVP Leaderboard Tab -->
    <div class="tab-content" id="tab-mvps">
        <div class="card">
            <?php if (!empty($mvpLeaderboard)): ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th class="rankings-col-rank">#</th><th>Jugador</th><th>Equipo</th><th class="rankings-center">MVPs</th><th class="rankings-center">Torneos</th></tr></thead>
                    <tbody>
                    <?php foreach ($mvpLeaderboard as $i => $mv): $pos = $i + 1; ?>
                    <tr class="<?= $pos <= 3 ? 'rank-' . $pos : '' ?>">
                        <td>
                            <?php if ($pos <= 3): ?>
                                <span class="rank-medal"><?= $pos ?></span>
                            <?php else: ?>
                                <span class="rank-position"><?= $pos ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="rankings-player-cell">
                            <?php if ($mv['avatar']): ?><img src="<?= UPLOAD_URL . $mv['avatar'] ?>" class="rankings-player-avatar"><?php endif; ?>
                            <a href="<?= url('user/view/' . $mv['id']) ?>" class="rankings-player-name rankings-player-link"><?= htmlspecialchars($mv['ml_nickname'] ?? $mv['username']) ?></a>
                        </td>
                        <td><?= $mv['team_name'] ? '<span class="badge badge-purple">' . htmlspecialchars($mv['team_tag'] ?? $mv['team_name']) . '</span>' : '-' ?></td>
                        <td class="rankings-center"><strong class="rankings-mvp-count"><?= $mv['mvp_count'] ?></strong> <span class="rankings-mvp-label">MVP<?= $mv['mvp_count'] !== 1 ? 's' : '' ?></span></td>
                        <td class="rankings-center rankings-muted-cell"><?= $mv['tournaments_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state rankings-empty-state">
                <div class="empty-state-icon">🏅</div>
                <h3 class="empty-state-title">Sin MVPs registrados</h3>
                <p>Los MVPs aparecerán aquí cuando se registren resultados de partidas.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
