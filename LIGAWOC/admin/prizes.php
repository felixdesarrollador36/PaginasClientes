<?php
/**
 * Liga WOC - Admin Prize Management
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
if (!isAdmin()) redirect('dashboard');
$pageTitle = 'Gestionar Premios';
$page = 'admin';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $db->insert("INSERT INTO prizes (tournament_id, position, prize_type, description, value) VALUES (?, ?, ?, ?, ?)",
            [intval($_POST['tournament_id']), intval($_POST['position']), $_POST['prize_type'], sanitize($_POST['description']), sanitize($_POST['value'] ?? '')]);
        setFlash('success', 'Premio creado.');
    } elseif ($act === 'deliver') {
        $db->update("UPDATE prizes SET is_delivered = 1, delivered_at = NOW() WHERE id = ?", [intval($_POST['prize_id'])]);
        setFlash('success', 'Premio marcado como entregado.');
    } elseif ($act === 'delete') {
        $db->delete("DELETE FROM prizes WHERE id = ?", [intval($_POST['prize_id'])]);
        setFlash('success', 'Premio eliminado.');
    }
    redirect('admin/prizes');
}

$prizes = $db->fetchAll("SELECT p.*, t.name as tournament_name, tm.name as team_name FROM prizes p LEFT JOIN tournaments t ON p.tournament_id = t.id LEFT JOIN teams tm ON p.awarded_to_team_id = tm.id ORDER BY p.created_at DESC");
$tournaments = $db->fetchAll("SELECT id, name FROM tournaments ORDER BY created_at DESC LIMIT 20");

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content">
    <div class="page-header"><h1 class="page-title">🎁 Gestionar Premios</h1></div>

    <div class="card mb-3">
        <h3 class="card-title mb-2">➕ Nuevo Premio</h3>
        <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Torneo*</label>
                    <select name="tournament_id" class="form-control" required>
                    <?php foreach ($tournaments as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Posición*</label><input type="number" name="position" class="form-control" min="1" value="1" required></div>
                <div class="form-group"><label class="form-label">Tipo*</label>
                    <select name="prize_type" class="form-control"><option value="money">💰 Dinero</option><option value="diamonds">💎 Diamantes ML</option><option value="skin">🎨 Skin</option><option value="other">🎁 Otro</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:2;"><label class="form-label">Descripción*</label><input type="text" name="description" class="form-control" required placeholder="Ej: $100 USD primer lugar"></div>
                <div class="form-group"><label class="form-label">Valor</label><input type="text" name="value" class="form-control" placeholder="$100"></div>
            </div>
            <button type="submit" class="btn btn-primary">💾 Crear Premio</button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>Torneo</th><th>Pos.</th><th>Tipo</th><th>Descripción</th><th>Equipo</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($prizes as $p): ?>
                <tr>
                    <td style="font-size:0.85rem;"><?= htmlspecialchars($p['tournament_name'] ?? 'N/A') ?></td>
                    <td><?php if($p['position']==1) echo '🥇'; elseif($p['position']==2) echo '🥈'; elseif($p['position']==3) echo '🥉'; else echo '#'.$p['position']; ?></td>
                    <td><span class="badge badge-purple"><?= ucfirst($p['prize_type']) ?></span></td>
                    <td><?= htmlspecialchars($p['description']) ?></td>
                    <td><?= $p['team_name'] ? htmlspecialchars($p['team_name']) : '<span class="text-muted">Sin asignar</span>' ?></td>
                    <td><?= $p['is_delivered'] ? '<span class="badge badge-green">✅ Entregado</span>' : '<span class="badge badge-yellow">⏳ Pendiente</span>' ?></td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <?php if (!$p['is_delivered']): ?>
                            <form method="POST" style="display:inline;"><?= csrfField() ?><input type="hidden" name="action" value="deliver"><input type="hidden" name="prize_id" value="<?= $p['id'] ?>"><button class="btn btn-sm btn-success">✅</button></form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="prize_id" value="<?= $p['id'] ?>"><button class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar?')">🗑️</button></form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
