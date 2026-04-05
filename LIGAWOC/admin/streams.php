<?php
/**
 * Liga WOC - Admin Stream Management
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
if (!isAdmin()) redirect('dashboard');
$pageTitle = 'Gestionar Streams';
$page = 'admin';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $db->insert("INSERT INTO streams (title, url, platform, tournament_id, is_live, scheduled_at) VALUES (?, ?, ?, ?, ?, ?)",
            [sanitize($_POST['title']), sanitize($_POST['url']), $_POST['platform'], intval($_POST['tournament_id']) ?: null, isset($_POST['is_live']) ? 1 : 0, $_POST['scheduled_at'] ?: null]);
        setFlash('success', 'Stream creado.');
    } elseif ($act === 'toggle') {
        $db->update("UPDATE streams SET is_live = NOT is_live WHERE id = ?", [intval($_POST['stream_id'])]);
        setFlash('success', 'Estado actualizado.');
    } elseif ($act === 'delete') {
        $db->delete("DELETE FROM streams WHERE id = ?", [intval($_POST['stream_id'])]);
        setFlash('success', 'Stream eliminado.');
    }
    redirect('admin/streams');
}

$streams = $db->fetchAll("SELECT s.*, t.name as tournament_name FROM streams s LEFT JOIN tournaments t ON s.tournament_id = t.id ORDER BY s.is_live DESC, s.created_at DESC");
$tournaments = $db->fetchAll("SELECT id, name FROM tournaments WHERE status IN ('registration','ready','in_progress') ORDER BY name");

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content">
    <div class="page-header"><h1 class="page-title">📺 Gestionar Streams</h1></div>

    <div class="card mb-3">
        <h3 class="card-title mb-2">➕ Nuevo Stream</h3>
        <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group" style="flex:2;"><label class="form-label">Título*</label><input type="text" name="title" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Plataforma</label>
                    <select name="platform" class="form-control"><option value="youtube">YouTube</option><option value="twitch">Twitch</option><option value="facebook">Facebook</option><option value="other">Otro</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:2;"><label class="form-label">URL*</label><input type="url" name="url" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Torneo</label>
                    <select name="tournament_id" class="form-control"><option value="">Sin torneo</option>
                    <?php foreach ($tournaments as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Programado para</label><input type="datetime-local" name="scheduled_at" class="form-control"></div>
                <div class="form-group" style="display:flex;align-items:end;"><label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:12px;"><input type="checkbox" name="is_live" style="accent-color:var(--danger);"> 🔴 En Vivo ahora</label></div>
            </div>
            <button type="submit" class="btn btn-primary">💾 Crear Stream</button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>Título</th><th>Plataforma</th><th>Torneo</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($streams as $s): ?>
                <tr>
                    <td><a href="<?= htmlspecialchars($s['url']) ?>" target="_blank" style="font-weight:600;"><?= htmlspecialchars($s['title']) ?></a></td>
                    <td><span class="badge badge-purple"><?= ucfirst($s['platform']) ?></span></td>
                    <td style="font-size:0.8rem;"><?= htmlspecialchars($s['tournament_name'] ?? '-') ?></td>
                    <td><?= $s['is_live'] ? '<span class="live-indicator"><span class="live-dot"></span> LIVE</span>' : '<span class="badge badge-yellow">Offline</span>' ?></td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <form method="POST" style="display:inline;"><?= csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="stream_id" value="<?= $s['id'] ?>"><button type="submit" class="btn btn-sm <?= $s['is_live'] ? 'btn-danger' : 'btn-success' ?>"><?= $s['is_live'] ? '⏹️' : '▶️' ?></button></form>
                            <form method="POST" style="display:inline;"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="stream_id" value="<?= $s['id'] ?>"><button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar?')">🗑️</button></form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
