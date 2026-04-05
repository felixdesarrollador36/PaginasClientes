<?php
$pageTitle = 'Equipos';
$page = 'teams';
$pageCss = 'teams';
$teamCtrl = new TeamController();
$search = $_GET['q'] ?? '';
$currentPageNum = max(1, intval($_GET['p'] ?? 1));
$result = $teamCtrl->getAllTeams($currentPageNum, $search);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content teams-main">
    <div class="page-header teams-header">
        <div class="teams-header-copy">
            <h1 class="page-title">👥 Equipos</h1>
            <p class="page-subtitle">Encuentra tu equipo ideal o crea el tuyo</p>
        </div>
        <?php if (!isAdmin() && !isSuperAdmin()): ?>
        <a href="<?= url('teams/create') ?>" class="btn btn-primary">➕ Crear Equipo</a>
        <?php endif; ?>
    </div>

    <!-- Search -->
    <form method="GET" action="<?= url('teams') ?>" class="teams-search-form">
        <div class="teams-search-row">
            <input type="text" name="q" class="form-control" placeholder="🔍 Buscar equipo..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-secondary">Buscar</button>
        </div>
    </form>

    <?php if (!empty($result['teams'])): ?>
    <div class="grid grid-3 teams-grid">
        <?php foreach ($result['teams'] as $team): ?>
        <a href="<?= url('teams/view/' . $team['id']) ?>" class="team-card teams-card-link">
            
            <div class="team-card-header">
                <?php if ($team['logo']): ?>
                    <img src="<?= UPLOAD_URL . $team['logo'] ?>" class="team-logo" alt="">
                <?php else: ?>
                    <div class="team-logo-placeholder"><?= strtoupper(substr($team['name'], 0, 2)) ?></div>
                <?php endif; ?>
                
                <?php if ($team['member_count'] < $team['max_members']): ?>
                    <div class="team-recruiting-badge">
                        <span class="pulse-dot"></span> Reclutando
                    </div>
                <?php endif; ?>
            </div>

            <div class="team-card-body">
                <h3 class="team-name"><?= htmlspecialchars($team['name']) ?></h3>
                <?php if ($team['tag']): ?>
                    <div class="team-tag">[<?= htmlspecialchars($team['tag']) ?>]</div>
                <?php endif; ?>
            </div>

            <div class="team-stats">
                <div class="team-stat-item">
                    <div class="team-stat-value"><?= $team['member_count'] ?>/<?= $team['max_members'] ?></div>
                    <div class="team-stat-label">Miembros</div>
                </div>
                <div class="team-stat-item">
                    <div class="team-stat-value text-success"><?= $team['wins'] ?></div>
                    <div class="team-stat-label">V <span class="hide-mobile">ictorias</span></div>
                </div>
                <div class="team-stat-item">
                    <div class="team-stat-value text-danger"><?= $team['losses'] ?></div>
                    <div class="team-stat-label">D <span class="hide-mobile">errotas</span></div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($result['pages'] > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
        <a href="<?= url('teams?p=' . $i . ($search ? '&q=' . urlencode($search) : '')) ?>" class="page-link <?= $i == $currentPageNum ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state teams-empty-state">
        <div class="empty-state-icon">👥</div>
        <h3 class="empty-state-title">No hay equipos <?= $search ? 'que coincidan' : 'aún' ?></h3>
        <p>¡Sé el primero en crear uno!</p>
        <?php if (!isAdmin() && !isSuperAdmin()): ?>
        <a href="<?= url('teams/create') ?>" class="btn btn-primary mt-2">➕ Crear Equipo</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
