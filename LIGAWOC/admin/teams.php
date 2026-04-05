<?php
/**
 * Liga WOC - Admin Team Management
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/TeamController.php';
if (!isAdmin()) redirect('dashboard');
$pageTitle = 'Gestionar Equipos';
$page = 'admin';

$db = Database::getInstance();

if (isset($_GET['action']) && isset($_GET['tid'])) {
    verifyCsrfRequest();
    $tid = intval($_GET['tid']);
    if ($_GET['action'] === 'verify') { $db->update("UPDATE teams SET is_verified = 1 WHERE id = ?", [$tid]); setFlash('success', 'Equipo verificado.'); }
    elseif ($_GET['action'] === 'deactivate') { $db->update("UPDATE teams SET is_active = 0 WHERE id = ?", [$tid]); setFlash('success', 'Equipo desactivado.'); }
    elseif ($_GET['action'] === 'activate') { $db->update("UPDATE teams SET is_active = 1 WHERE id = ?", [$tid]); setFlash('success', 'Equipo reactivado.'); }
    redirect('admin/teams');
}

$teamCtrl = new TeamController();
$search = $_GET['q'] ?? '';
$currentPageNum = max(1, intval($_GET['p'] ?? 1));
$viewTeamId = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;

if ($viewTeamId) {
    $selectedTeam = $db->fetch("SELECT t.*, u.username as captain_name FROM teams t LEFT JOIN users u ON t.captain_id = u.id WHERE t.id = ?", [$viewTeamId]);
    $members = $db->fetchAll(
        "SELECT u.id, u.username, u.email, u.whatsapp, u.discord, u.phone_brand, u.ml_id, u.ml_server, u.ml_nickname, u.is_verified, u.is_banned, u.created_at, tm.role as member_role, tm.joined_at
         FROM team_members tm
         JOIN users u ON tm.user_id = u.id
         WHERE tm.team_id = ?
         ORDER BY tm.role = 'captain' DESC, tm.joined_at ASC",
        [$viewTeamId]
    );
}

$params = [];
$where = "WHERE 1=1";

if ($search) {
    $where .= " AND (t.name LIKE ? OR t.tag LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$total = $db->fetch("SELECT COUNT(*) as total FROM teams t $where", $params)['total'];
$params[] = ITEMS_PER_PAGE;
$params[] = ($currentPageNum - 1) * ITEMS_PER_PAGE;

$teams = $db->fetchAll(
    "SELECT t.*, u.username as captain_name, 
            (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count,
            COALESCE((SELECT COUNT(*) FROM tournament_matches WHERE winner_id = t.id AND status = 'completed'), 0) as dyn_wins,
            COALESCE((SELECT COUNT(*) FROM tournament_matches WHERE loser_id = t.id AND status = 'completed'), 0) as dyn_losses
     FROM teams t 
     LEFT JOIN users u ON t.captain_id = u.id 
     $where 
     ORDER BY dyn_wins DESC, t.created_at DESC 
     LIMIT ? OFFSET ?",
    $params
);

foreach ($teams as &$team) {
    $team['wins'] = $team['dyn_wins'];
    $team['losses'] = $team['dyn_losses'];
    $team['points'] = $team['dyn_wins'] * 3;
}
$result = ['teams' => $teams, 'total' => $total, 'pages' => ceil($total / ITEMS_PER_PAGE)];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content">
    <div class="page-header"><h1 class="page-title">👥 Gestionar Equipos</h1></div>
    <form method="GET" action="<?= url('admin/teams') ?>" style="margin-bottom:20px;">
        <div style="display:flex;gap:10px;max-width:400px;"><input type="text" name="q" class="form-control" placeholder="🔍 Buscar..." value="<?= htmlspecialchars($search) ?>"><button type="submit" class="btn btn-secondary">Buscar</button></div>
    </form>
    <div class="card">
        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>Equipo</th><th>Capitán</th><th>Miembros</th><th>V/D</th><th>Puntos</th><th>Estado</th><th>Ver</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($result['teams'] as $t): ?>
                <tr>
                    <td style="font-weight:600;"><a href="<?= url('teams/view/' . $t['id']) ?>"><?= htmlspecialchars($t['name']) ?></a></td>
                    <td><?= htmlspecialchars($t['captain_name']) ?></td>
                    <td><?= $t['member_count'] ?>/<?= $t['max_members'] ?></td>
                    <td><span class="text-success"><?= $t['wins'] ?></span>/<span class="text-danger"><?= $t['losses'] ?></span></td>
                    <td><strong class="text-accent"><?= $t['points'] ?></strong></td>
                    <td>
                        <?= $t['is_verified'] ? '<span class="badge badge-green">✅</span>' : '<span class="badge badge-yellow">⏳</span>' ?>
                        <?= !$t['is_active'] ? '<span class="badge badge-red">Inactivo</span>' : '' ?>
                    </td>
                    <td>
                        <a href="<?= url('admin/teams?team_id=' . $t['id']) ?>" class="btn btn-sm btn-secondary">👥</a>
                    </td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <?php if (!$t['is_verified']): ?><a href="<?= url('admin/teams?action=verify&tid=' . $t['id'] . '&csrf_token=' . csrfToken()) ?>" class="btn btn-sm btn-success">✅</a><?php endif; ?>
                            <?php if ($t['is_active']): ?><a href="<?= url('admin/teams?action=deactivate&tid=' . $t['id'] . '&csrf_token=' . csrfToken()) ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Desactivar equipo?')">🚫</a>
                            <?php else: ?><a href="<?= url('admin/teams?action=activate&tid=' . $t['id'] . '&csrf_token=' . csrfToken()) ?>" class="btn btn-sm btn-success">🔄</a><?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($result['pages'] > 1): ?>
    <div style="display:flex;justify-content:center;gap:8px;margin-top:20px;">
        <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
            <a href="<?= url('admin/teams?p=' . $p . ($search ? '&q=' . urlencode($search) : '')) ?>" class="btn btn-sm <?= $p == $currentPageNum ? 'btn-primary' : 'btn-secondary' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php if ($viewTeamId && $selectedTeam): ?>
    <div style="margin-top: 30px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <h2 class="card-title">👥 Miembros de <?= htmlspecialchars($selectedTeam['name']) ?></h2>
            <a href="<?= url('admin/teams') ?>" class="btn btn-secondary">← Volver</a>
        </div>
        
        <div class="card">
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Jugador</th><th>Contacto / Info</th><th>Rol</th><th>Estado</th><th>Registro</th></tr></thead>
                    <tbody>
                    <?php foreach ($members as $m): ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($m['username']) ?><br><span style="font-size:0.75rem;font-weight:normal;color:var(--text-muted);"> IGN: <?= htmlspecialchars($m['ml_nickname'] ?? 'N/A') ?></span></td>
                        <td style="font-size:0.75rem;">
                            <?php if ($m['whatsapp']): ?><div style="margin-bottom:2px;"><strong style="color:#25D366;">WA:</strong> <?= htmlspecialchars($m['whatsapp']) ?></div><?php endif; ?>
                            <?php if ($m['discord']): ?><div style="margin-bottom:2px;"><strong style="color:#5865F2;">DS:</strong> <?= htmlspecialchars($m['discord']) ?></div><?php endif; ?>
                            <?php if ($m['phone_brand']): ?><div style="margin-bottom:2px;"><strong style="color:var(--text-muted)">Cel:</strong> <?= htmlspecialchars($m['phone_brand']) ?></div><?php endif; ?>
                            <?php if ($m['ml_id'] || $m['ml_server']): ?><div><strong style="color:#F97316;">ML:</strong> <?= htmlspecialchars($m['ml_id'] ?? '-') ?> (<?= htmlspecialchars($m['ml_server'] ?? '-') ?>)</div><?php endif; ?>
                            <?php if (empty($m['whatsapp']) && empty($m['discord']) && empty($m['phone_brand']) && empty($m['ml_id']) && empty($m['ml_server'])): ?><span style="color:var(--text-muted);font-style:italic;">Incompleto</span><?php endif; ?>
                        </td>
                        <td><?= $m['member_role'] === 'captain' ? '<span class="badge badge-yellow">👑 Capitán</span>' : '<span class="badge badge-blue">Jugador</span>' ?></td>
                        <td>
                            <?php if ($m['is_banned']): ?><span class="badge badge-red">🚫 Baneado</span>
                            <?php elseif ($m['is_verified']): ?><span class="badge badge-green">✅ Verificado</span>
                            <?php else: ?><span class="badge badge-yellow">⏳ Pendiente</span><?php endif; ?>
                        </td>
                        <td style="font-size:0.8rem;"><?= date('d M Y', strtotime($m['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
