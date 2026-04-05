<?php
/**
 * Liga WOC - SuperAdmin Panel
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
if (!isSuperAdmin()) redirect('admin');
$pageTitle = 'SuperAdmin';
$page = 'admin';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = $_POST['action'] ?? '';
    if ($act === 'update_config') {
        foreach ($_POST['config'] as $key => $value) {
            $db->update("UPDATE platform_config SET config_value = ? WHERE config_key = ?", [sanitize($value), $key]);
        }
        setFlash('success', 'Configuración actualizada.');
    } elseif ($act === 'create_season') {
        $db->update("UPDATE seasons SET is_active = 0 WHERE is_active = 1");
        $db->insert("INSERT INTO seasons (name, start_date, is_active, description) VALUES (?, ?, 1, ?)",
            [sanitize($_POST['name']), $_POST['start_date'], sanitize($_POST['description'] ?? '')]);
        setFlash('success', 'Nueva temporada creada.');
    }
    redirect('admin/superadmin');
}

$configs = $db->fetchAll("SELECT * FROM platform_config ORDER BY config_key");
$admins = $db->fetchAll("SELECT id, username, email, role, created_at, last_login FROM users WHERE role IN ('admin','superadmin') ORDER BY role DESC, username");
$seasons = $db->fetchAll("SELECT * FROM seasons ORDER BY start_date DESC");
$logs = $db->fetchAll("SELECT al.*, u.username FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 30");

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content">
    <div class="page-header"><h1 class="page-title">🔱 Panel SuperAdmin</h1></div>

    <div class="grid grid-2">
        <!-- Platform Config -->
        <div class="card">
            <h3 class="card-title mb-2">⚙️ Configuración Global</h3>
            <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="update_config">
                <?php foreach ($configs as $c): ?>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars($c['description'] ?: $c['config_key']) ?></label>
                    <input type="text" name="config[<?= $c['config_key'] ?>]" class="form-control" value="<?= htmlspecialchars($c['config_value']) ?>">
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary btn-sm">💾 Guardar</button>
            </form>
        </div>

        <!-- Admin List -->
        <div>
            <div class="card mb-2">
                <h3 class="card-title mb-2">👑 Administradores</h3>
                <?php foreach ($admins as $a): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border-color);">
                    <div>
                        <span style="font-weight:600;"><?= htmlspecialchars($a['username']) ?></span>
                        <span class="badge badge-<?= $a['role'] === 'superadmin' ? 'yellow' : 'blue' ?>" style="font-size:0.65rem;"><?= $a['role'] ?></span>
                        <div style="font-size:0.75rem;color:var(--text-muted);"><?= $a['email'] ?></div>
                    </div>
                    <span style="font-size:0.75rem;color:var(--text-muted);"><?= $a['last_login'] ? timeAgo($a['last_login']) : 'Nunca' ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Seasons -->
            <div class="card">
                <h3 class="card-title mb-2">📅 Temporadas</h3>
                <?php foreach ($seasons as $s): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-color);">
                    <span style="font-weight:<?= $s['is_active'] ? '700' : '400' ?>;"><?= htmlspecialchars($s['name']) ?> <?= $s['is_active'] ? '<span class="badge badge-green">Activa</span>' : '' ?></span>
                    <span style="font-size:0.75rem;color:var(--text-muted);"><?= date('d M Y', strtotime($s['start_date'])) ?></span>
                </div>
                <?php endforeach; ?>
                <form method="POST" style="margin-top:12px;"><?= csrfField() ?><input type="hidden" name="action" value="create_season">
                    <div class="form-row">
                        <div class="form-group"><input type="text" name="name" class="form-control" placeholder="Temporada 2" required></div>
                        <div class="form-group"><input type="date" name="start_date" class="form-control" required></div>
                        <div class="form-group"><button type="submit" class="btn btn-primary btn-sm btn-block">➕</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Audit Logs -->
    <div class="card mt-3">
        <h3 class="card-title mb-2">📋 Logs del Sistema</h3>
        <?php if (!empty($logs)): ?>
        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $l): ?>
                <tr>
                    <td style="font-size:0.8rem;"><?= date('d/m/Y H:i', strtotime($l['created_at'])) ?></td>
                    <td><?= htmlspecialchars($l['username'] ?? 'Sistema') ?></td>
                    <td><span class="badge badge-purple"><?= htmlspecialchars($l['action']) ?></span></td>
                    <td style="font-size:0.8rem;"><?= $l['entity_type'] ? $l['entity_type'] . ' #' . $l['entity_id'] : '-' ?></td>
                    <td style="font-size:0.75rem;color:var(--text-muted);"><?= $l['ip_address'] ?? '-' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:20px 0;"><p>Sin logs registrados</p></div>
        <?php endif; ?>
    </div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
