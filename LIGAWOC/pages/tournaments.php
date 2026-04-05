<?php
$pageTitle = 'Torneos';
$page = 'tournaments';
$tournCtrl = new TournamentController();
$status = $_GET['status'] ?? null;
$currentPageNum = max(1, intval($_GET['p'] ?? 1));
$result = $tournCtrl->getAll($currentPageNum, $status);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content">
    <div class="page-header tournaments-page-header">
        <div>
            <h1 class="page-title">Torneos</h1>
            <p class="page-subtitle">Inscríbete y compite con tu equipo</p>
        </div>
        <?php if (isAdmin() || isSuperAdmin()): ?>
        <a href="<?= url('admin/tournaments') ?>" class="btn btn-primary">⚙️ Administrar Torneos</a>
        <?php endif; ?>
    </div>

    <div class="tabs mb-3">
        <a href="<?= url('tournaments') ?>" class="tab <?= !$status ? 'active' : '' ?>">Todos</a>
        <a href="<?= url('tournaments?status=registration') ?>" class="tab <?= $status === 'registration' ? 'active' : '' ?>">Inscripciones</a>
        <a href="<?= url('tournaments?status=in_progress') ?>" class="tab <?= $status === 'in_progress' ? 'active' : '' ?>">En Curso</a>
        <a href="<?= url('tournaments?status=completed') ?>" class="tab <?= $status === 'completed' ? 'active' : '' ?>">Finalizados</a>
    </div>

    <?php if (!empty($result['tournaments'])): ?>
    <div class="grid grid-2">
        <?php foreach ($result['tournaments'] as $t): ?>
        <div class="tournament-card">
            <div class="tournament-card-header">
                <div class="tournaments-card-head-row">
                    <h3 class="tournaments-card-title"><?= htmlspecialchars($t['name']) ?></h3>
                    <span class="tournament-status <?= $t['status'] ?>">
                        <?php
                        $statusLabels = ['draft' => 'Borrador', 'registration' => 'Inscripciones', 'ready' => 'Listo', 'in_progress' => 'En Curso', 'completed' => 'Finalizado', 'cancelled' => 'Cancelado'];
                        echo $statusLabels[$t['status']] ?? $t['status'];
                        ?>
                    </span>
                </div>
            </div>
            <div class="tournament-card-body">
                <div class="tournaments-card-meta-grid">
                    <span class="tournament-info-item"><strong>Formato:</strong> <?= TOURNAMENT_FORMATS[$t['format']] ?? $t['format'] ?></span>
                    <span class="tournament-info-item"><strong>Equipos:</strong> <?= $t['registered_teams'] ?>/<?= $t['max_teams'] ?></span>
                    <?php if ($t['start_date']): ?><span class="tournament-info-item"><strong>Inicio:</strong> <?= date('d M Y', strtotime($t['start_date'])) ?></span><?php endif; ?>
                    <?php if ($t['prize_pool']): ?><span class="tournament-info-item"><strong>Premios:</strong> <?= htmlspecialchars($t['prize_pool']) ?></span><?php endif; ?>
                    <?php if ($t['entry_fee'] > 0): ?><span class="tournament-info-item"><strong>Inscripción:</strong> <?= number_format($t['entry_fee'], 2) ?></span><?php endif; ?>
                    <span class="tournament-info-item"><strong>Modo:</strong> <?= $t['team_size'] ?>v<?= $t['team_size'] ?></span>
                </div>
                <?php if ($t['description']): ?>
                <p class="tournaments-card-description">
                    <?= htmlspecialchars($t['description']) ?>
                </p>
                <?php endif; ?>
            </div>
            <div class="tournament-card-footer">
                <span class="tournaments-season-name"><?= $t['season_name'] ?? 'Sin temporada' ?></span>
                <a href="<?= url('tournaments/view/' . $t['id']) ?>" class="btn btn-primary btn-sm">Ver Detalles</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($result['pages'] > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
        <a href="<?= url('tournaments?p=' . $i . ($status ? '&status=' . urlencode($status) : '')) ?>" class="page-link <?= $i == $currentPageNum ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state">
        <div class="empty-state-icon">
            <svg class="icon-svg lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4a2 2 0 01-2-2V5h4M18 9h2a2 2 0 002-2V5h-4"/><path d="M6 5a6 6 0 0012 0"/><path d="M12 15v4M8 19h8"/><rect x="6" y="3" width="12" height="2" rx="1"/></svg>
        </div>
        <h3 class="empty-state-title">No hay torneos<?= $status ? ' en esta categoría' : '' ?></h3>
        <p>Pronto se anunciarán nuevos torneos</p>
    </div>
    <?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
