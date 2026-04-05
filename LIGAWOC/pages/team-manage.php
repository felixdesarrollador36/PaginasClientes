<?php
$page = 'teams';
$pageCss = 'team-manage';
$teamId = $_GET['id'] ?? 0;
$teamCtrl = new TeamController();
$team = $teamCtrl->getTeam($teamId);
if (!$team || $team['captain_id'] != currentUserId()) { redirect('teams'); }
$members = $teamCtrl->getTeamMembers($teamId);
$pendingRequests = $teamCtrl->getPendingRequests($teamId);
$pageTitle = 'Gestionar ' . $team['name'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div
    class="main-content team-manage-page"
    data-csrf-token="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>"
    data-api-base="<?= htmlspecialchars(url('api/teams/'), ENT_QUOTES) ?>"
    data-kick-member-base="<?= htmlspecialchars(url('api/teams/kick-member/'), ENT_QUOTES) ?>"
    data-change-role-base="<?= htmlspecialchars(url('api/teams/change-role/'), ENT_QUOTES) ?>"
>
    <a href="<?= url('teams/view/' . $teamId) ?>" class="team-manage-back-link">← Volver al equipo</a>
    <div class="page-header"><h1 class="page-title">⚙️ Gestionar <?= htmlspecialchars($team['name']) ?></h1></div>

    <!-- Pending Requests -->
    <div class="card mb-3">
        <div class="card-header">
            <h2 class="card-title">📋 Solicitudes Pendientes (<?= count($pendingRequests) ?>)</h2>
        </div>
        <?php if (empty($pendingRequests)): ?>
            <div class="empty-state team-manage-empty-state"><p>No hay solicitudes pendientes</p></div>
        <?php else: ?>
            <?php foreach ($pendingRequests as $req): ?>
            <div class="team-manage-request-card" id="request-<?= $req['id'] ?>">
                <div class="team-manage-request-header">
                    <div class="user-avatar-placeholder team-manage-avatar-placeholder team-manage-avatar-placeholder--request"><?= strtoupper(substr($req['username'], 0, 1)) ?></div>
                    <div>
                        <strong><?= htmlspecialchars($req['username']) ?></strong> - <span class="team-manage-request-ign"><?= htmlspecialchars($req['ml_nickname']) ?></span>
                        <div class="team-manage-request-time"><?= timeAgo($req['created_at']) ?></div>
                    </div>
                </div>
                <div class="team-manage-request-meta">
                    <span>🎮 <strong>ID:</strong> <?= $req['ml_id'] ?> (<?= $req['ml_server'] ?>)</span>
                    <span>⚔️ <strong>Rol:</strong> <?= ML_ROLES[$req['role']] ?? $req['role'] ?></span>
                    <span>🛤️ <strong>Líneas:</strong> <?= ML_LANES[$req['lane_1']] ?? '' ?><?= $req['lane_2'] ? ' / ' . (ML_LANES[$req['lane_2']] ?? '') : '' ?></span>
                    <span>🦸 <strong>Main:</strong> <?= htmlspecialchars($req['main_hero']) ?></span>
                    <?php if ($req['current_rank']): ?><span>🏅 <strong>Rango:</strong> <?= ML_RANKS[$req['current_rank']] ?? $req['current_rank'] ?></span><?php endif; ?>
                </div>
                <?php if ($req['message']): ?>
                <p class="team-manage-request-message">"<?= htmlspecialchars($req['message']) ?>"</p>
                <?php endif; ?>
                <div class="team-manage-request-actions">
                    <button type="button" class="btn btn-success btn-sm" data-request-action="approve" data-request-id="<?= $req['id'] ?>">✅ Aceptar</button>
                    <button type="button" class="btn btn-danger btn-sm" data-request-action="reject" data-request-id="<?= $req['id'] ?>">❌ Rechazar</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Current Members -->
    <div class="card mb-3">
        <div class="card-header">
            <h2 class="card-title">👥 Miembros del Equipo (<?= count($members) ?>)</h2>
        </div>
        <?php foreach ($members as $member): ?>
        <div class="team-manage-member-row" id="member-<?= $member['user_id'] ?>">
            <div class="team-manage-member-main">
                <?php if ($member['avatar']): ?>
                    <img src="<?= UPLOAD_URL . $member['avatar'] ?>" class="team-manage-avatar-image" alt="<?= htmlspecialchars($member['username']) ?>">
                <?php else: ?>
                    <div class="user-avatar-placeholder team-manage-avatar-placeholder"><?= strtoupper(substr($member['username'], 0, 1)) ?></div>
                <?php endif; ?>
                <div>
                    <div class="team-manage-member-name">
                        <?= htmlspecialchars(!empty($member['ml_nickname']) ? $member['ml_nickname'] : $member['username']) ?>
                        <?= $member['is_captain'] ? '<span class="badge badge-yellow team-manage-captain-badge">👑 Capitán</span>' : '' ?>
                    </div>
                    <div class="team-manage-member-meta">
                        <span class="badge badge-purple team-manage-role-badge" id="role-badge-<?= $member['user_id'] ?>"><?= ML_ROLES[$member['role']] ?? $member['role'] ?></span>
                        <?= ML_LANES[$member['lane_1']] ?? '' ?><?= $member['lane_2'] ? ' / ' . (ML_LANES[$member['lane_2']] ?? '') : '' ?>
                    </div>
                </div>
            </div>
            <div class="team-manage-member-actions">
                <button
                    type="button"
                    class="btn btn-secondary btn-sm team-manage-action-btn"
                    title="Cambiar rol"
                    data-open-role-modal
                    data-user-id="<?= $member['user_id'] ?>"
                    data-member-name="<?= htmlspecialchars($member['ml_nickname'] ?? $member['username'], ENT_QUOTES) ?>"
                    data-current-role="<?= htmlspecialchars($member['role'], ENT_QUOTES) ?>"
                >🎭 Rol</button>
                <?php if (!$member['is_captain']): ?>
                <button
                    type="button"
                    class="btn btn-danger btn-sm team-manage-action-btn"
                    data-kick-member
                    data-user-id="<?= $member['user_id'] ?>"
                    data-member-name="<?= htmlspecialchars($member['ml_nickname'] ?? $member['username'], ENT_QUOTES) ?>"
                >🚫 Expulsar</button>
                <button
                    type="button"
                    class="btn btn-warning btn-sm team-manage-action-btn transfer-leadership-btn"
                    data-transfer-leader
                    data-user-id="<?= $member['user_id'] ?>"
                    data-member-name="<?= htmlspecialchars($member['ml_nickname'] ?? $member['username'], ENT_QUOTES) ?>"
                >👑 Transferir liderazgo</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Edit Team -->
    <div class="card">
        <div class="card-header"><h2 class="card-title">✏️ Editar Equipo</h2></div>
        <form method="POST" action="<?= url('teams/update/' . $teamId) ?>" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($team['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Tag</label>
                    <input type="text" name="tag" class="form-control" value="<?= htmlspecialchars($team['tag'] ?? '') ?>" maxlength="10">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Descripción</label>
                <textarea name="description" class="form-control"><?= htmlspecialchars($team['description'] ?? '') ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Región</label>
                    <input type="text" name="region" class="form-control" value="<?= htmlspecialchars($team['region'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Cambiar Logo</label>
                    <input type="file" name="logo" class="form-control" accept="image/*">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">💾 Guardar Cambios</button>
        </form>
    </div>
</div></div>

<!-- Role Change Modal -->
<div id="roleModal" class="team-manage-role-modal">
    <div class="card team-manage-role-modal-card">
        <button type="button" class="team-manage-modal-close" data-close-role-modal>&times;</button>
        <h3 class="team-manage-modal-title">🎭 Cambiar Rol</h3>
        <p class="team-manage-modal-text">Asignando nuevo rol a <strong id="roleMemberName"></strong></p>
        <div class="form-group">
            <label class="form-label">Nuevo Rol</label>
            <select id="roleSelect" class="form-control team-manage-role-select">
                <?php foreach (ML_ROLES as $k => $v): ?>
                <option value="<?= $k ?>"><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            require_once __DIR__ . '/../controllers/TeamController.php';
        </div>
        <div class="team-manage-modal-actions">
            <button type="button" class="btn btn-glass" data-close-role-modal>Cancelar</button>
            <button type="button" class="btn btn-primary" data-submit-role-change>💾 Guardar</button>
        </div>
    </div>
</div>

<!-- Transfer Leadership Modal -->
<div id="transferLeadershipModal" class="team-manage-role-modal" style="display:none;">
    <div class="card team-manage-role-modal-card">
        <button type="button" class="team-manage-modal-close" id="closeTransferLeadershipModal">&times;</button>
        <h3 class="team-manage-modal-title">👑 Transferir Liderazgo</h3>
        <p class="team-manage-modal-text">¿Estás seguro de que deseas transferir el liderazgo a <strong id="transferLeaderMemberName"></strong>? Esta acción no se puede deshacer.</p>
        <div class="team-manage-modal-actions">
            <button type="button" class="btn btn-glass" id="cancelTransferLeadership">Cancelar</button>
            <button type="button" class="btn btn-warning" id="confirmTransferLeadership">Transferir</button>
        </div>
    </div>
</div>

<script>
let transferUserId = null;
document.querySelectorAll('.transfer-leadership-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        transferUserId = this.getAttribute('data-user-id');
        document.getElementById('transferLeaderMemberName').textContent = this.getAttribute('data-member-name');
        document.getElementById('transferLeadershipModal').style.display = 'block';
    });
});
document.getElementById('closeTransferLeadershipModal').onclick =
document.getElementById('cancelTransferLeadership').onclick = function() {
    document.getElementById('transferLeadershipModal').style.display = 'none';
    transferUserId = null;
};
document.getElementById('confirmTransferLeadership').onclick = function() {
    if (!transferUserId) return;
    const csrf = document.querySelector('.main-content.team-manage-page').getAttribute('data-csrf-token');
    fetch('<?= url('teams/transfer-leadership/' . $teamId) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin', // <-- Solución para enviar cookies de sesión
        body: JSON.stringify({ user_id: transferUserId, csrf_token: csrf })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Liderazgo transferido exitosamente.');
            window.location.reload();
        } else {
            alert(data.error || 'Error al transferir liderazgo.');
        }
    })
    .catch(() => alert('Error al transferir liderazgo.'));
    document.getElementById('transferLeadershipModal').style.display = 'none';
    transferUserId = null;
};
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
