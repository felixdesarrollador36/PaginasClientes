<?php
/**
 * Liga WOC - Admin Dashboard
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
if (!isAdmin()) redirect('dashboard');
-require_once __DIR__ . '/../libs/ErrorHandler.php';
$pageTitle = 'Admin Panel';
$page = 'admin';

$db = Database::getInstance();
$totalUsers = $db->fetch("SELECT COUNT(*) as c FROM users")['c'];
$totalTeams = $db->fetch("SELECT COUNT(*) as c FROM teams WHERE is_active = 1")['c'];
$totalTournaments = $db->fetch("SELECT COUNT(*) as c FROM tournaments")['c'];
$activeTournaments = $db->fetch("SELECT COUNT(*) as c FROM tournaments WHERE status IN ('registration','in_progress')")['c'];
$totalNews = $db->fetch("SELECT COUNT(*) as c FROM news")['c'];
$recentUsers = $db->fetchAll("SELECT id, username, email, role, created_at, last_login FROM users ORDER BY created_at DESC LIMIT 5");
$recentMatches = $db->fetchAll("SELECT tm.*, t1.name as team1_name, t2.name as team2_name, tr.name as tournament_name FROM tournament_matches tm LEFT JOIN teams t1 ON tm.team1_id = t1.id LEFT JOIN teams t2 ON tm.team2_id = t2.id LEFT JOIN tournaments tr ON tm.tournament_id = tr.id WHERE tm.status = 'completed' ORDER BY tm.completed_at DESC LIMIT 5");

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">⚙️ Panel de Administración</h1>
        <p class="page-subtitle">Gestiona Liga WOC desde aquí</p>
    </div>

    <!-- Stats -->
    <div class="grid grid-4 mb-3">
        <div class="stat-card"><div class="stat-icon purple">👤</div><div><div class="stat-value"><?= $totalUsers ?></div><div class="stat-label">Usuarios</div></div></div>
        <div class="stat-card"><div class="stat-icon green">👥</div><div><div class="stat-value"><?= $totalTeams ?></div><div class="stat-label">Equipos</div></div></div>
        <div class="stat-card"><div class="stat-icon blue">🏆</div><div><div class="stat-value"><?= $activeTournaments ?>/<?= $totalTournaments ?></div><div class="stat-label">Torneos Activos</div></div></div>
        <div class="stat-card"><div class="stat-icon orange">📰</div><div><div class="stat-value"><?= $totalNews ?></div><div class="stat-label">Noticias</div></div></div>
    </div>

    <!-- Quick Actions -->
    <div class="card mb-3">
        <h3 class="card-title mb-2">🚀 Acciones Rápidas</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="<?= url('admin/news') ?>" class="btn btn-primary btn-sm">📰 Gestionar Noticias</a>
            <a href="<?= url('admin/tournaments') ?>" class="btn btn-primary btn-sm">🏆 Gestionar Torneos</a>
            <a href="<?= url('admin/users') ?>" class="btn btn-secondary btn-sm">👤 Gestionar Usuarios</a>
            <a href="<?= url('admin/teams') ?>" class="btn btn-secondary btn-sm">👥 Gestionar Equipos</a>
            <a href="<?= url('admin/streams') ?>" class="btn btn-secondary btn-sm">📺 Streams</a>
        </div>
    </div>

    <div class="grid grid-2">
        <!-- Recent Users -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">👤 Usuarios Recientes</h3></div>
            <?php foreach ($recentUsers as $u): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border-color);">
                <div>
                    <span style="font-weight:600;"><?= htmlspecialchars($u['username']) ?></span>
                    <span class="badge badge-purple" style="font-size:0.65rem;margin-left:4px;"><?= $u['role'] ?></span>
                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= $u['email'] ?></div>
                </div>
                <span style="font-size:0.75rem;color:var(--text-muted);"><?= timeAgo($u['created_at']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Matches -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">⚔️ Partidas Recientes</h3></div>
            <?php if (!empty($recentMatches)): ?>
                <?php foreach ($recentMatches as $m): ?>
                <div style="padding:10px 0;border-bottom:1px solid var(--border-color);">
                    <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:4px;">🏆 <?= htmlspecialchars($m['tournament_name'] ?? '') ?></div>
                    <div style="font-weight:600;font-size:0.9rem;">
                        <?= htmlspecialchars($m['team1_name'] ?? 'TBD') ?>
                        <span style="color:var(--neon-orchid);margin:0 8px;"><?= $m['team1_score'] ?> - <?= $m['team2_score'] ?></span>
                        <?= htmlspecialchars($m['team2_name'] ?? 'TBD') ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="padding:20px 0;"><p>Sin partidas recientes</p></div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
