<?php
/**
 * Liga WOC — Landing Page (Challonge / MLBB Style)
 */
$pageTitle = 'Bienvenido';
$pageCss = 'home';

$db = Database::getInstance();

// 1. Current Active Season & Main Tournament
$activeSeason = $db->fetch("SELECT id, name FROM seasons WHERE is_active = 1 LIMIT 1");
$mainTournament = null;
if ($activeSeason) {
    $mainTournament = $db->fetch("SELECT id, name, status, start_date, max_teams,
                                  (SELECT COUNT(*) FROM tournament_teams WHERE tournament_id = tournaments.id AND status = 'registered') as registered_teams
                                  FROM tournaments 
                                  WHERE is_main_tournament = 1 AND season_id = ? 
                                  LIMIT 1", [$activeSeason['id']]);
}

// 2. Live Matches
$liveMatches = $db->fetchAll(
    "SELECT m.*, t1.name as t1_name, t1.logo as t1_logo, t1.tag as t1_tag, 
            t2.name as t2_name, t2.logo as t2_logo, t2.tag as t2_tag, tr.name as tournament_name
     FROM tournament_matches m
     LEFT JOIN teams t1 ON m.team1_id = t1.id
     LEFT JOIN teams t2 ON m.team2_id = t2.id
     LEFT JOIN tournaments tr ON m.tournament_id = tr.id
     WHERE m.status = 'live'
     ORDER BY m.started_at DESC LIMIT 3"
);

// 3. Recent Results
$recentMatches = $db->fetchAll(
    "SELECT m.*, t1.name as t1_name, t1.logo as t1_logo, t2.name as t2_name, t2.logo as t2_logo, tr.name as tournament_name
     FROM tournament_matches m
     LEFT JOIN teams t1 ON m.team1_id = t1.id
     LEFT JOIN teams t2 ON m.team2_id = t2.id
     LEFT JOIN tournaments tr ON m.tournament_id = tr.id
     WHERE m.status = 'completed'
     ORDER BY m.completed_at DESC LIMIT 5"
);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-container home-root">
    
    <!-- Hero Section -->
    <div class="hero-section home-hero">
        <div class="hero-logo">
            <svg class="icon-svg xl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 11h4M8 9v4M15 12h.01M18 10h.01"/><rect x="2" y="6" width="20" height="12" rx="2"/>
            </svg>
        </div>
        <h1 class="hero-title">
            Tu Torneo.<br>
            <span class="accent">Tu Victoria.</span>
        </h1>
        <p class="hero-subtitle">
            La plataforma oficial de torneos para <strong>Mobile Legends: Bang Bang</strong>.<br>
            Organiza brackets, gestiona equipos y compite por la gloria.
        </p>

        <!-- CTA -->
        <div class="hero-cta">
            <a href="<?= url('register') ?>" class="btn btn-primary btn-lg">Registrarse</a>
            <a href="<?= url('login') ?>" class="btn btn-outline btn-lg">Iniciar Sesión</a>
        </div>
    </div>

    <!-- Active Season Banner -->
    <?php if ($activeSeason && $mainTournament): ?>
    <div class="home-section">
        <div class="card season-card">
            <div class="season-glow"></div>
            <div class="season-inner">
                <div>
                    <span class="badge badge-yellow season-badge">⭐ TEMPORADA ACTIVA</span>
                    <h2 class="season-title"><?= htmlspecialchars($mainTournament['name']) ?></h2>
                    <p class="season-meta"><?= htmlspecialchars($activeSeason['name']) ?> • <?= $mainTournament['registered_teams'] ?>/<?= $mainTournament['max_teams'] ?> Equipos</p>
                </div>
                <div class="season-action">
                    <a href="<?= url('tournaments/view/' . $mainTournament['id']) ?>" class="btn btn-primary">Ver Torneo</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Live & Recent Activity -->
    <div class="home-activity">
        
        <!-- Live Matches -->
        <?php if (!empty($liveMatches)): ?>
        <div>
            <h3 class="activity-heading">
                <span class="live-dot"></span>
                Partidas en Vivo
            </h3>
            <div class="live-list">
                <?php foreach ($liveMatches as $lm): ?>
                <div class="card match-card-live">
                    <div class="match-meta"><?= htmlspecialchars((string)($lm['tournament_name'] ?? '')) ?> • R<?= (int)($lm['round'] ?? 0) ?></div>
                    <div class="match-row">
                        <div class="match-team">
                            <?php if ($lm['t1_logo']): ?><img src="<?= UPLOAD_URL . $lm['t1_logo'] ?>" class="match-team-logo"><?php endif; ?>
                            <div class="match-team-name"><?= htmlspecialchars((string)($lm['t1_tag'] ?: ($lm['t1_name'] ?? ''))) ?></div>
                        </div>
                        <div class="match-vs">VS</div>
                        <div class="match-team">
                            <?php if ($lm['t2_logo']): ?><img src="<?= UPLOAD_URL . $lm['t2_logo'] ?>" class="match-team-logo"><?php endif; ?>
                            <div class="match-team-name"><?= htmlspecialchars((string)($lm['t2_tag'] ?: ($lm['t2_name'] ?? ''))) ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Results -->
        <?php if (!empty($recentMatches)): ?>
        <div>
            <h3 class="activity-heading">📜 Últimos Resultados</h3>
            <div class="card results-card">
                <?php foreach ($recentMatches as $idx => $rm): ?>
                <div class="result-row">
                    <div class="result-inner">
                        <div class="result-meta"><?= htmlspecialchars((string)($rm['tournament_name'] ?? '')) ?></div>
                        <div class="result-teams">
                            <span class="result-team-name <?= $rm['winner_id']==$rm['team1_id'] ? 'winner' : '' ?>"><?= htmlspecialchars((string)($rm['t1_name'] ?? '')) ?></span>
                            <span class="result-score"><?= $rm['team1_score'] ?> - <?= $rm['team2_score'] ?></span>
                            <span class="result-team-name <?= $rm['winner_id']==$rm['team2_id'] ? 'winner' : '' ?>"><?= htmlspecialchars((string)($rm['t2_name'] ?? '')) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <a href="<?= url('match-history') ?>" class="results-more">Ver historial completo →</a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Feature cards -->
    <div class="hero-features features-notop">
        <div class="grid grid-3 grid-left">
            <div class="feature-card">
                <div class="icon-box md purple">
                    <svg class="icon-svg lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 9H4a2 2 0 01-2-2V5h4M18 9h2a2 2 0 002-2V5h-4"/><path d="M6 5a6 6 0 0012 0"/><path d="M12 15v4M8 19h8"/><rect x="6" y="3" width="12" height="2" rx="1"/>
                    </svg>
                </div>
                <h3>Torneos Oficiales</h3>
                <p>Brackets automáticos, eliminación simple o doble, y gestión de resultados en tiempo real.</p>
            </div>
            <div class="feature-card">
                <div class="icon-box md purple">
                    <svg class="icon-svg lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
                    </svg>
                </div>
                <h3>Rosters</h3>
                <p>Crea tu equipo, asigna roles y lanes, vincula IDs de ML y compite como una escuadra profesional.</p>
            </div>
            <div class="feature-card">
                <div class="icon-box md purple">
                    <svg class="icon-svg lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>
                    </svg>
                </div>
                <h3>Rankings</h3>
                <p>Tabla de posiciones por temporada. Descubre quién es el mejor jugador y equipo de la liga.</p>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="hero-stats">
        <?php
        try {
            $usersCount = $db->fetch("SELECT COUNT(*) as c FROM users")['c'] ?? 0;
            $teamsCount = $db->fetch("SELECT COUNT(*) as c FROM teams WHERE is_active = 1")['c'] ?? 0;
            $tournsCount = $db->fetch("SELECT COUNT(*) as c FROM tournaments")['c'] ?? 0;
        } catch(Exception $e) {
            $usersCount = 0; $teamsCount = 0; $tournsCount = 0;
        }
        ?>
        <div class="hero-stat"><div class="hero-stat-value"><?= $usersCount ?></div><div class="hero-stat-label">Jugadores</div></div>
        <div class="hero-stat"><div class="hero-stat-value"><?= $teamsCount ?></div><div class="hero-stat-label">Equipos</div></div>
        <div class="hero-stat"><div class="hero-stat-value"><?= $tournsCount ?></div><div class="hero-stat-label">Torneos</div></div>
    </div>

    <div class="hero-footer">
        &copy; <?= date('Y') ?> Liga WOC &mdash; Powering Esports
    </div>
</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
