<?php
/**
 * Liga WOC - Admin User Management
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../libs/AuditLogger.php';

if (!isAdmin() && !isModerator()) redirect('dashboard');
$pageTitle = 'Gestionar Usuarios';
$page = 'admin';

$db = Database::getInstance();

// Handle actions
if (isset($_GET['action']) && isset($_GET['uid'])) {
    // Verificar CSRF token para prevenir ataques
    verifyCsrfRequest();
    
    $uid = intval($_GET['uid']);
    $act = $_GET['action'];
    
    if ($act === 'ban') { 
        $db->update("UPDATE users SET is_banned = 1 WHERE id = ? AND role = 'player'", [$uid]); 
        auditLog('USER_BAN', 'user', $uid, 'is_banned=0', 'is_banned=1', 'Usuario baneado por violación de reglas');
        setFlash('success', 'Usuario baneado.'); 
    }
    elseif ($act === 'unban') { 
        $db->update("UPDATE users SET is_banned = 0 WHERE id = ?", [$uid]); 
        auditLog('USER_UNBAN', 'user', $uid, 'is_banned=1', 'is_banned=0', 'Usuario desbaneado');
        setFlash('success', 'Usuario desbaneado.'); 
    }
    elseif ($act === 'promote' && isAdmin()) { 
        $db->update("UPDATE users SET role = 'admin' WHERE id = ? AND role IN ('player', 'designer')", [$uid]); 
        auditLog('USER_PROMOTE', 'user', $uid, 'role=player', 'role=admin', 'Usuario promovido a administrator');
        setFlash('success', 'Usuario promovido a admin.'); 
    }
    elseif ($act === 'demote' && isAdmin()) { 
        $db->update("UPDATE users SET role = 'player' WHERE id = ? AND role = 'admin'", [$uid]); 
        auditLog('USER_DEMOTE', 'user', $uid, 'role=admin', 'role=player', 'Admin degradado a jugador');
        setFlash('success', 'Admin degradado a player.'); 
    }
    elseif ($act === 'delete' && (isAdmin() || isSuperAdmin())) {
        // Get user info first
        $userToDelete = $db->fetch("SELECT id, role FROM users WHERE id = ?", [$uid]);
        
        if (!$userToDelete) {
            setFlash('error', 'Usuario no encontrado.');
        } elseif ($userToDelete['role'] === 'superadmin') {
            setFlash('error', 'No se puede eliminar a un superadmin.');
        } else {
            // Full delete - first remove from related tables
            $db->delete("DELETE FROM match_comments WHERE user_id = ?", [$uid]);
            $db->delete("DELETE FROM match_player_stats WHERE user_id = ?", [$uid]);
            $db->delete("DELETE FROM team_join_requests WHERE user_id = ?", [$uid]);
            $db->delete("DELETE FROM team_members WHERE user_id = ?", [$uid]);
            
            // If user is captain, delete their teams too
            $teams = $db->fetchAll("SELECT id FROM teams WHERE captain_id = ?", [$uid]);
            foreach ($teams as $team) {
                $db->delete("DELETE FROM tournament_teams WHERE team_id = ?", [$team['id']]);
                $db->delete("DELETE FROM team_members WHERE team_id = ?", [$team['id']]);
                $db->delete("DELETE FROM teams WHERE id = ?", [$team['id']]);
            }
            
            $db->delete("DELETE FROM notifications WHERE user_id = ?", [$uid]);
            $db->delete("DELETE FROM user_inventory WHERE user_id = ?", [$uid]);
            $db->delete("DELETE FROM coin_purchases WHERE user_id = ?", [$uid]);
            $db->delete("DELETE FROM users WHERE id = ?", [$uid]);
            
            auditLog('USER_DELETE', 'user', $uid, null, null, 'Usuario eliminado completamente');
            setFlash('success', 'Usuario y todos sus datos eliminados.');
        }
    }
    elseif ($act === 'edit_mlid' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $ml_id = sanitize($_POST['ml_id'] ?? '');
        $ml_server = sanitize($_POST['ml_server'] ?? '');
        $ml_nickname = sanitize($_POST['ml_nickname'] ?? '');
        if (!empty($ml_id) && !preg_match('/^\d+$/', $ml_id)) {
            setFlash('error', 'El ML ID solo puede contener números.');
            redirect('admin/users');
        }
        if (!empty($ml_server) && !preg_match('/^\d+$/', $ml_server)) {
            setFlash('error', 'El Server solo puede contener números.');
            redirect('admin/users');
        }
        $db->update("UPDATE users SET ml_id = ?, ml_server = ?, ml_nickname = ? WHERE id = ?", [$ml_id, $ml_server, $ml_nickname, $uid]);
        auditLog('USER_EDIT_MLID', 'user', $uid, null, ['ml_id' => $ml_id, 'ml_server' => $ml_server, 'ml_nickname' => $ml_nickname], 'Datos de MLBB actualizados');
        setFlash('success', 'Datos de MLBB del usuario actualizados.');
    }
    elseif ($act === 'add_coins' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
        $coins = intval($_POST['coins'] ?? 0);
        if ($coins > 0) {
            $db->update(
                "INSERT INTO user_coins (user_id, coins, lifetime_coins) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE coins = coins + ?, lifetime_coins = lifetime_coins + ?",
                [$uid, $coins, $coins, $coins, $coins]
            );
            auditLog('USER_ADD_COINS', 'user', $uid, null, $coins, "Se agregaron {$coins} WOC Coins al usuario");
            setFlash('success', "Se agregaron $coins WOC Coins al usuario.");
        }
    }
    elseif ($act === 'remove_coins' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
        $coins = intval($_POST['coins'] ?? 0);
        if ($coins > 0) {
            $current = $db->fetch("SELECT coins FROM user_coins WHERE user_id = ?", [$uid]);
            $currentCoins = $current ? $current['coins'] : 0;
            $removeCoins = min($coins, $currentCoins);
            $db->update("UPDATE user_coins SET coins = coins - ? WHERE user_id = ?", [$removeCoins, $uid]);
            setFlash('success', "Se quitaron $removeCoins WOC Coins al usuario.");
        }
    }
    elseif ($act === 'edit_user' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
        $new_username = sanitize($_POST['username'] ?? '');
        $new_role = sanitize($_POST['role'] ?? '');
        $new_phone_brand = sanitize($_POST['phone_brand'] ?? '');
        
        $updated = false;
        
        if (!empty($new_username)) {
            $check = $db->fetch("SELECT id FROM users WHERE username = ? AND id != ?", [$new_username, $uid]);
            if (!$check) {
                $db->update("UPDATE users SET username = ? WHERE id = ?", [$new_username, $uid]);
                $updated = true;
            } else {
                setFlash('error', 'El nombre de usuario ya existe.');
            }
        }
        
        if (!empty($new_role) && in_array($new_role, ['player', 'moderator', 'admin', 'designer'])) {
            $db->update("UPDATE users SET role = ? WHERE id = ? AND role != 'superadmin'", [$new_role, $uid]);
            $updated = true;
        }
        
        if (isset($_POST['phone_brand'])) {
            $db->update("UPDATE users SET phone_brand = ? WHERE id = ?", [$new_phone_brand, $uid]);
            $updated = true;
        }
        
        if ($updated && !isset($_SESSION['flash'])) {
            setFlash('success', 'Usuario actualizado correctamente.');
        }
    }
    redirect('admin/users');
}

// Add coins to all users
if (isset($_GET['action']) && $_GET['action'] === 'add_all_coins' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $coins = intval($_POST['coins'] ?? 0);
    if ($coins > 0) {
        $users = $db->fetchAll("SELECT id FROM users");
        foreach ($users as $user) {
            $db->update(
                "INSERT INTO user_coins (user_id, coins, lifetime_coins) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE coins = coins + ?, lifetime_coins = lifetime_coins + ?",
                [$user['id'], $coins, $coins, $coins, $coins]
            );
        }
        setFlash('success', "Se agregaron $coins WOC Coins a todos los usuarios.");
    }
    redirect('admin/users');
}

$auth = new AuthController();
$search = $_GET['q'] ?? '';
$currentPageNum = max(1, intval($_GET['p'] ?? 1));
$result = $auth->getAllUsers($currentPageNum, $search);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h1 class="page-title">👤 Gestionar Usuarios</h1>
        <?php if (isAdmin()): ?>
        <button onclick="document.getElementById('allCoinsModal').style.display='flex'" class="btn btn-warning">🪙 Agregar a Todos</button>
        <?php endif; ?>
    </div>

    <form method="GET" action="<?= url('admin/users') ?>" style="margin-bottom:20px;">
        <div style="display:flex;gap:10px;max-width:400px;">
            <input type="text" name="q" class="form-control" placeholder="🔍 Buscar usuario..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-secondary">Buscar</button>
        </div>
    </form>

    <div class="card">
        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>ID</th><th>Usuario</th><th>Email</th><th>Contacto / Info</th><th>WOC</th><th>Rol</th><th>Estado</th><th>Registro</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($result['users'] as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($u['username']) ?><br><span style="font-size:0.75rem;font-weight:normal;color:var(--text-muted);"> IGN: <?= htmlspecialchars($u['ml_nickname'] ?? 'N/A') ?></span></td>
                    <td style="font-size:0.8rem;" title="<?= canViewFullEmail($u['id']) ? htmlspecialchars($u['email']) : 'Solo visible para Superadmin' ?>">
                        <?= canViewFullEmail($u['id']) ? htmlspecialchars($u['email']) : maskEmail($u['email']) ?>
                    </td>
                    <td style="font-size:0.75rem;">
                        <?php if ($u['whatsapp']): ?><div style="margin-bottom:2px;"><strong style="color:#25D366;">WA:</strong> <span title="<?= canViewFullEmail($u['id']) ? htmlspecialchars($u['whatsapp']) : 'Enmascarado' ?>"><?= canViewFullEmail($u['id']) ? htmlspecialchars($u['whatsapp']) : maskPhone($u['whatsapp']) ?></span></div><?php endif; ?>
                        <?php if ($u['discord']): ?><div style="margin-bottom:2px;"><strong style="color:#5865F2;">DS:</strong> <span title="<?= canViewFullEmail($u['id']) ? htmlspecialchars($u['discord']) : 'Enmascarado' ?>"><?= canViewFullEmail($u['id']) ? htmlspecialchars($u['discord']) : maskDiscord($u['discord']) ?></span></div><?php endif; ?>
                        <?php if ($u['phone_brand']): ?><div><strong style="color:var(--text-muted)">Cel:</strong> <?= htmlspecialchars($u['phone_brand']) ?></div><?php endif; ?>
                        <div style="margin-bottom:2px;"><strong style="color:#F59E0B;">ID:</strong> <?= htmlspecialchars($u['ml_id'] ?: 'N/A') ?></div>
                        <div><strong style="color:#FB923C;">Srv:</strong> <?= htmlspecialchars($u['ml_server'] ?: 'N/A') ?></div>
                        <?php if (empty($u['whatsapp']) && empty($u['discord']) && empty($u['phone_brand']) && empty($u['ml_id']) && empty($u['ml_server'])): ?><span style="color:var(--text-muted);font-style:italic;">Incompleto</span><?php endif; ?>
                    </td>
                    <?php 
                    $userCoins = $db->fetch("SELECT coins FROM user_coins WHERE user_id = ?", [$u['id']]);
                    $coins = $userCoins ? $userCoins['coins'] : 0;
                    ?>
                    <td>
                        <span style="color:#FFD700;font-weight:700;">🪙 <?= number_format($coins) ?></span>
                        <?php if (isAdmin()): ?>
                        <button onclick="openCoinsModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>', 'add')" class="btn btn-sm btn-secondary" style="padding:2px 6px;font-size:0.7rem;margin-left:4px;">+</button>
                        <button onclick="openCoinsModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>', 'remove')" class="btn btn-sm btn-danger" style="padding:2px 6px;font-size:0.7rem;">-</button>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= $u['role'] === 'superadmin' ? 'yellow' : ($u['role'] === 'admin' ? 'blue' : ($u['role'] === 'moderator' ? 'green' : 'purple')) ?>"><?= ucfirst($u['role']) ?></span></td>
                    <td>
                        <?php if ($u['is_banned']): ?><span class="badge badge-red">🚫 Baneado</span>
                        <?php elseif ($u['is_verified']): ?><span class="badge badge-green">✅ Verificado</span>
                        <?php else: ?><span class="badge badge-yellow">⏳ Pendiente</span><?php endif; ?>
                    </td>
                    <td style="font-size:0.8rem;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <div style="display:flex;gap:4px;flex-wrap:wrap;">
                            <?php if ($u['role'] === 'player'): ?>
                                <?php if (!$u['is_banned']): ?>
                                    <a href="<?= url('admin/users?action=ban&uid=' . $u['id'] . '&csrf_token=' . csrfToken()) ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Banear a este usuario?')">🚫</a>
                                <?php else: ?>
                                    <a href="<?= url('admin/users?action=unban&uid=' . $u['id'] . '&csrf_token=' . csrfToken()) ?>" class="btn btn-sm btn-success">✅</a>
                                <?php endif; ?>
                                <?php if (isAdmin()): ?>
                                    <a href="<?= url('admin/users?action=promote&uid=' . $u['id'] . '&csrf_token=' . csrfToken()) ?>" class="btn btn-sm btn-secondary" onclick="return confirm('¿Promover a admin?')">👑</a>
                                <?php endif; ?>
                            <?php elseif ($u['role'] === 'designer' && isAdmin()): ?>
                                <a href="<?= url('admin/users?action=promote&uid=' . $u['id'] . '&csrf_token=' . csrfToken()) ?>" class="btn btn-sm btn-secondary" onclick="return confirm('¿Promover a admin?')">👑</a>
                            <?php elseif ($u['role'] === 'admin' && isAdmin()): ?>
                                <a href="<?= url('admin/users?action=demote&uid=' . $u['id'] . '&csrf_token=' . csrfToken()) ?>" class="btn btn-sm btn-secondary" onclick="return confirm('¿Degradar a player?')">⬇️</a>
                            <?php endif; ?>
                            
                            <?php if ($u['role'] !== 'superadmin'): ?>
                                <?php if (isAdmin()): ?>
                                <button onclick="openUserEditModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>', '<?= $u['role'] ?>', '<?= htmlspecialchars($u['phone_brand'] ?? '') ?>')" class="btn btn-sm btn-primary" title="Editar Usuario">👤</button>
                                <?php endif; ?>
                                <button onclick="openEditModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['ml_id'] ?? '') ?>', '<?= htmlspecialchars($u['ml_server'] ?? '') ?>', '<?= htmlspecialchars($u['ml_nickname'] ?? '') ?>')" class="btn btn-sm btn-secondary" title="Editar MLBB ID">✏️</button>
                                <?php if (isAdmin()): ?>
                                <a href="<?= url('admin/users?action=delete&uid=' . $u['id'] . '&csrf_token=' . csrfToken()) ?>" class="btn btn-sm btn-danger" onclick="return confirm('¡CUIDADO! ¿Estás seguro de eliminar este usuario y TODOS sus datos? Esta acción no se puede deshacer.')" title="Eliminar usuario">🗑️</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($result['pages'] > 1): ?>
        <style>
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; padding-bottom: 20px; }
        .page-link { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 6px; background: rgba(255, 255, 255, 0.05); color: var(--text-color, #fff); text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: all 0.2s ease; border: 1px solid rgba(255, 255, 255, 0.1); }
        .page-link:hover { background: rgba(255, 255, 255, 0.1); border-color: rgba(255, 255, 255, 0.2); }
        .page-link.active { background: var(--border-color, #7C3AED); color: #fff; border-color: var(--border-color, #7C3AED); cursor: default; }
        </style>
        <div class="pagination">
            <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                <a href="<?= url('admin/users?p=' . $i . ($search ? '&q=' . urlencode($search) : '')) ?>" class="page-link <?= $i == $currentPageNum ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit MLBB ID Modal -->
<div id="editModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:500px; padding:24px; position:relative;">
        <button onclick="document.getElementById('editModal').style.display='none'" style="position:absolute; top:16px; right:16px; background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">&times;</button>
        <h3 class="card-title" style="margin-bottom:20px;">Editar Datos MLBB</h3>
        <form id="editForm" method="POST" action="">
            <?= csrfField() ?>
            <div class="form-row">
                <div class="form-group w-50">
                    <label class="form-label">ML ID</label>
                    <input type="text" name="ml_id" id="modal_mlid" class="form-control" inputmode="numeric" pattern="[0-9]+" title="Solo se permiten números">
                </div>
                <div class="form-group w-50">
                    <label class="form-label">Server</label>
                    <input type="text" name="ml_server" id="modal_mlserver" class="form-control" inputmode="numeric" pattern="[0-9]+" title="Solo se permiten números">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">IGN / Nickname</label>
                <input type="text" name="ml_nickname" id="modal_mlnick" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">💾 Guardar Cambios</button>
        </form>
    </div>
</div>

<script>
function openUserEditModal(uid, username, role, phoneBrand) {
    document.getElementById('userEditForm').action = '<?= url("admin/users") ?>?action=edit_user&uid=' + uid;
    document.getElementById('modal_username').value = username;
    document.getElementById('modal_role').value = role;
    document.getElementById('modal_phone_brand').value = phoneBrand || '';
    document.getElementById('userEditModal').style.display = 'flex';
}

function openEditModal(uid, mlid, mlserver, mlnick) {
    document.getElementById('editForm').action = '<?= url("admin/users") ?>?action=edit_mlid&uid=' + uid;
    document.getElementById('modal_mlid').value = mlid;
    document.getElementById('modal_mlserver').value = mlserver;
    document.getElementById('modal_mlnick').value = mlnick;
    document.getElementById('editModal').style.display = 'flex';
}

(function() {
    ['modal_mlid', 'modal_mlserver'].forEach(function(id) {
        var field = document.getElementById(id);
        if (!field) return;
        field.addEventListener('input', function() {
            this.value = this.value.replace(/\D+/g, '');
        });
        field.addEventListener('keypress', function(e) {
            if (e.ctrlKey || e.metaKey || e.altKey || e.key.length !== 1) return;
            if (!/[0-9]/.test(e.key)) e.preventDefault();
        });
        field.addEventListener('paste', function(e) {
            e.preventDefault();
            var pasted = (e.clipboardData || window.clipboardData).getData('text');
            this.value = pasted.replace(/\D+/g, '');
        });
    });
})();

function openCoinsModal(uid, username, type) {
    const modal = document.getElementById('coinsModal');
    const title = document.getElementById('coinsModalTitle');
    const btn = document.getElementById('coinsSubmitBtn');
    
    if (type === 'remove') {
        document.getElementById('coinsForm').action = '<?= url("admin/users") ?>?action=remove_coins&uid=' + uid;
        title.textContent = '🪙 Quitar WOC Coins';
        btn.textContent = '🗑️ Quitar Monedas';
        btn.className = 'btn btn-danger';
    } else {
        document.getElementById('coinsForm').action = '<?= url("admin/users") ?>?action=add_coins&uid=' + uid;
        title.textContent = '🪙 Agregar WOC Coins';
        btn.textContent = '🪙 Agregar Monedas';
        btn.className = 'btn btn-primary';
    }
    
    document.getElementById('coinsUsername').textContent = username;
    modal.style.display = 'flex';
}

function closeCoinsModal() {
    document.getElementById('coinsModal').style.display = 'none';
}
</script>

<!-- Add/Remove Coins Modal -->
<div id="coinsModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:400px; padding:24px; position:relative;">
        <button onclick="closeCoinsModal()" style="position:absolute; top:16px; right:16px; background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">&times;</button>
        <h3 class="card-title" id="coinsModalTitle" style="margin-bottom:20px;">🪙 Agregar WOC Coins</h3>
        <p style="margin-bottom:16px;">Usuario: <strong id="coinsUsername"></strong></p>
        <form id="coinsForm" method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label class="form-label">Cantidad de Monedas</label>
                <input type="number" name="coins" class="form-control" min="1" required placeholder="Ej: 100">
            </div>
            <button type="submit" id="coinsSubmitBtn" class="btn btn-primary" style="width:100%; margin-top:16px;">🪙 Agregar Monedas</button>
        </form>
    </div>
</div>

<!-- Add Coins to All Users -->
<div id="allCoinsModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:400px; padding:24px; position:relative;">
        <button onclick="document.getElementById('allCoinsModal').style.display='none'" style="position:absolute; top:16px; right:16px; background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">&times;</button>
        <h3 class="card-title" style="margin-bottom:20px;">🪙 Agregar a Todos los Usuarios</h3>
        <form method="POST" action="<?= url('admin/users?action=add_all_coins') ?>">
            <?= csrfField() ?>
            <div class="form-group">
                <label class="form-label">Cantidad de Monedas</label>
                <input type="number" name="coins" class="form-control" min="1" required placeholder="Ej: 50">
            </div>
            <button type="submit" class="btn btn-warning" style="width:100%; margin-top:16px;" onclick="return confirm('¿Estás seguro de agregar monedas a TODOS los usuarios?')">🪙 Agregar a Todos</button>
        </form>
    </div>
</div>

<!-- Edit User (Username & Role) Modal -->
<div id="userEditModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:400px; padding:24px; position:relative;">
        <button onclick="document.getElementById('userEditModal').style.display='none'" style="position:absolute; top:16px; right:16px; background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">&times;</button>
        <h3 class="card-title" style="margin-bottom:20px;">👤 Editar Usuario</h3>
        <form id="userEditForm" method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label class="form-label">Nombre de Usuario</label>
                <input type="text" name="username" id="modal_username" class="form-control" required placeholder="Nuevo nombre de usuario">
            </div>
            <div class="form-group">
                <label class="form-label">Modelo de Celular</label>
                <input type="text" name="phone_brand" id="modal_phone_brand" class="form-control" placeholder="Ej. iPhone, Samsung, Xiaomi">
            </div>
            <div class="form-group">
                <label class="form-label">Rol</label>
                <select name="role" id="modal_role" class="form-control">
                    <option value="player">Player</option>
                    <option value="moderator">Moderador</option>
                    <option value="admin">Admin</option>
                    <option value="designer">Designer</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:16px;">💾 Guardar Cambios</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

