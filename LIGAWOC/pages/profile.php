<?php
require_once __DIR__ . '/../controllers/ShopController.php';

$pageTitle = 'Mi Perfil';
$page = 'profile';
$pageCss = 'profile';
$auth = new AuthController();
$user = $auth->getUser(currentUserId());
$teamCtrl = new TeamController();
$myTeam = $teamCtrl->getUserTeam(currentUserId());

$shopCtrl = new ShopController();
$equippedItems = $shopCtrl->getEquippedItems(currentUserId());
$inventoryItems = $shopCtrl->getUserInventory(currentUserId());

$equippedMarco = null;
$equippedPortada = null;

foreach ($equippedItems as $eq) {
    if ($eq['type'] === 'marco') {
        $equippedMarco = $eq;
    } elseif ($eq['type'] === 'portada') {
        $equippedPortada = $eq;
    }
}

$marcoItems = array_filter($inventoryItems, function($item) {
    return $item['type'] === 'marco';
});

$portadaItems = array_filter($inventoryItems, function($item) {
    return $item['type'] === 'portada';
});

$emailPending = $_SESSION['email_change_pending'] ?? '';
$emailChangeSuccess = isset($_SESSION['email_change_success']);
unset($_SESSION['email_change_pending'], $_SESSION['email_change_success']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
echo '<link rel="stylesheet" href="' . url('assets/css/profile.css') . '?v=5">';
?>
<div class="app-wrapper">
<div
    class="main-content profile-page"
    data-csrf-token="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>"
    data-equip-marco-url="<?= htmlspecialchars(url('profile/equip-marco'), ENT_QUOTES) ?>"
    data-equip-portada-url="<?= htmlspecialchars(url('profile/equip-portada'), ENT_QUOTES) ?>"
    data-resend-email-url="<?= htmlspecialchars(url('profile/resend-email-code'), ENT_QUOTES) ?>"
    data-email-pending="<?= htmlspecialchars($emailPending, ENT_QUOTES) ?>"
    data-email-success="<?= $emailChangeSuccess ? '1' : '0' ?>"
>
    <div class="page-header profile-page-header">
        <h1 class="page-title" style="display:none;">👤 Mi Perfil</h1>
    </div>

    <?php
        $coverUrl = url('assets/img/pattern.png');
        if (!empty($user['cover'])) {
            $coverUrl = url('assets/covers/' . $user['cover']);
        }
        if (!empty($equippedPortada) && !empty($equippedPortada['image'])) {
            $coverUrl = url('assets/shop/' . $equippedPortada['image']);
        }

        $marcoStyle = '';
        if (!empty($equippedMarco) && !empty($equippedMarco['image'])) {
            $marcoStyle = 'padding:10px; background-image: url(' . url('assets/shop/' . $equippedMarco['image']) . '); background-size: cover; background-position: center;';
        }

        $roleBadgeClass = 'badge-purple';
        $roleStyleClass = '';
        if ($user['role'] === 'designer') {
            $roleBadgeClass = 'badge-yellow';
            $roleStyleClass = 'profile-role-badge--designer';
        } elseif ($user['role'] === 'admin') {
            $roleStyleClass = 'profile-role-badge--admin';
        }
    ?>

    <section class="profile-banner card mb-4">
        <div class="profile-banner-media">
            <img src="<?= $coverUrl ?>" class="profile-banner-cover" alt="Portada de perfil">
            <div class="profile-banner-scrim"></div>
            <button type="button" data-open-modal="portadaModal" class="btn btn-secondary profile-banner-cover-btn">
                <span aria-hidden="true">🖼️</span>
                <span>Cambiar portada</span>
            </button>
            <div class="profile-banner-body">
                <div class="profile-banner-avatar-stack">
                    <div class="profile-banner-avatar-shell" style="<?= $marcoStyle ?>">
                        <?php if ($user['avatar']): ?>
                            <img src="<?= UPLOAD_URL . $user['avatar'] ?>" class="profile-banner-avatar" alt="Avatar de <?= htmlspecialchars($user['username']) ?>">
                        <?php else: ?>
                            <div class="profile-banner-avatar-fallback"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-banner-copy">
                    <div class="profile-banner-headline-row">
                        <div>
                            <p class="profile-banner-kicker">Perfil de jugador</p>
                            <h2 class="profile-banner-name"><?= htmlspecialchars($user['username']) ?></h2>
                        </div>
                        <div class="profile-banner-badges">
                            <span class="badge profile-banner-role <?= $roleBadgeClass ?> <?= $roleStyleClass ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>
                            <?php if ($user['ml_nickname']): ?>
                                <span class="profile-banner-ign">IGN: <?= htmlspecialchars($user['ml_nickname']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="profile-banner-actions">
                        <label for="avatar-upload" class="btn btn-secondary profile-banner-action">
                            Cambiar avatar
                        </label>
                        <button type="button" data-open-modal="marcoModal" class="btn btn-secondary profile-banner-action">
                            Cambiar marco
                        </button>
                        <button type="button" data-open-modal="portadaModal" class="btn btn-secondary profile-banner-action">
                            Cambiar portada
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <div class="profile-layout-grid">
        <!-- Settings Form -->
        <div class="card profile-surface profile-surface--main">
            <div class="card-header border-bottom-0 pb-0 profile-surface-header">
                <h3 class="card-title profile-surface-title">
                    Ajustes de Perfil
                </h3>
                <p class="profile-surface-subtitle">Administra tu información personal y configuración de cuenta.</p>
            </div>
            
            <form method="POST" action="<?= url('profile') ?>" enctype="multipart/form-data" id="profile-form" style="padding: 20px;">
                <?= csrfField() ?>
                <input type="file" name="avatar" id="avatar-upload" class="d-none" accept="image/*" data-auto-submit-profile>

                <div class="form-row">
                    <div class="form-group w-50">
                        <label class="form-label">Usuario</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled style="height: 48px; opacity: 0.7; cursor: not-allowed;">
                    </div>
                    <div class="form-group w-50">
                        <label class="form-label">Email</label>
                        <div class="profile-email-edit-row">
                            <input type="email" class="form-control profile-email-input" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                            <button type="button" class="btn btn-secondary profile-email-edit-btn" data-open-modal="emailModal" aria-label="Editar email" title="Editar email">✏️</button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Biografía</label>
                    <textarea name="bio" class="form-control" rows="3" placeholder="Cuéntanos sobre tu estilo de juego..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>

                <hr class="esports-divider">
                
                <h3 style="font-size:1.05rem;font-weight:700;color:var(--accent-bright);margin-bottom:20px; font-family:var(--font-heading); text-transform:uppercase;">
                    Datos de Mobile Legends
                </h3>

                <div class="form-row">
                    <div class="form-group w-50">
                        <label class="form-label">ML ID</label>
                        <input type="text" name="ml_id" class="form-control" value="<?= htmlspecialchars($user['ml_id'] ?? '') ?>" disabled style="height: 48px; background-color: rgba(255,255,255,0.02); cursor: not-allowed; opacity: 0.7;">
                    </div>
                    <div class="form-group w-50">
                        <label class="form-label">Servidor (Server ID)</label>
                        <input type="text" name="ml_server" class="form-control" value="<?= htmlspecialchars($user['ml_server'] ?? '') ?>" disabled style="height: 48px; background-color: rgba(255,255,255,0.02); cursor: not-allowed; opacity: 0.7;">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nickname en el Juego (IGN)</label>
                    <input type="text" name="ml_nickname" class="form-control" value="<?= htmlspecialchars($user['ml_nickname'] ?? '') ?>" disabled style="height: 48px; background-color: rgba(255,255,255,0.02); cursor: not-allowed; opacity: 0.7;">
                </div>
                <div class="form-group">
                    <label class="form-label">Modelo de Celular</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['phone_brand'] ?? 'No registrado') ?>" disabled style="height: 48px; background-color: rgba(255,255,255,0.02); cursor: not-allowed; opacity: 0.7;">
                </div>

                <?php if ($myTeam): ?>
                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">🏅 Rango Actual de tu Equipo</label>
                    <div style="display:flex; align-items:flex-start; gap:16px; margin-top:8px;">
                        <?php if ($myTeam['current_rank'] && isset(ML_RANKS[$myTeam['current_rank']])): ?>
                            <div class="profile-rank-item active" title="<?= ML_RANKS[$myTeam['current_rank']] ?>" style="min-width: 100px; padding: 16px;">
                                <img src="<?= asset('RANGOS_IMG/' . ML_RANK_IMAGES[$myTeam['current_rank']]) ?>" alt="<?= ML_RANKS[$myTeam['current_rank']] ?>" style="width: 70px; height: 70px;">
                                <span style="font-size: 0.8rem; margin-top: 4px;"><?= ML_RANKS[$myTeam['current_rank']] ?></span>
                            </div>
                        <?php else: ?>
                            <div class="empty-state" style="padding: 20px !important; flex:1; text-align:left; display:flex; align-items:center; gap:12px;">
                                <div class="empty-state-icon" style="width:40px !important; height:40px !important; margin:0 !important;"><i class="fas fa-question text-accent-light"></i></div>
                                <div>
                                    <h4 style="font-family:var(--font-heading); color:#fff; font-size:0.95rem; margin:0 0 4px 0;">Sin Rango Asignado</h4>
                                    <p style="font-size:0.8rem; margin:0;">Contacta al capitán de tu equipo para actualizar el rango.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <hr class="esports-divider">
                
                <h3 style="font-size:1.05rem;font-weight:700;color:var(--accent-bright);margin-bottom:20px; font-family:var(--font-heading); text-transform:uppercase;">
                    Seguridad
                </h3>
                <div class="form-row">
                    <div class="form-group w-50">
                        <label class="form-label">Contraseña Actual</label>
                        <input type="password" name="current_password" class="form-control" placeholder="Solo si deseas cambiarla" style="height: 48px;">
                    </div>
                    <div class="form-group w-50">
                        <label class="form-label">Nueva Contraseña</label>
                        <input type="password" name="new_password" class="form-control" minlength="6" placeholder="Mínimo 6 caracteres" style="height: 48px;">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-4" style="width: 100%; padding: 16px; font-size: 1rem; font-weight: 800;">
                    GUARDAR CAMBIOS
                </button>
            </form>
        </div>

        <!-- Sidebar Info -->
        <div class="profile-sidebar-column">
            <div class="card profile-surface profile-surface--sidebar mb-4">
                <div class="card-header pb-0 border-0 profile-surface-header profile-surface-header--sidebar">
                    <h3 class="card-title profile-surface-title profile-surface-title--small">
                        INFO DE CUENTA
                    </h3>
                </div>
                
                <div class="profile-surface-body">
                    <div class="profile-info-item profile-info-card">
                        <div class="pi-label">Miembro desde</div>
                        <div class="pi-value text-accent-light"><?= date('d M Y', strtotime($user['created_at'])) ?></div>
                    </div>
                    
                    <?php if ($user['last_login']): ?>
                    <div class="profile-info-item profile-info-card">
                        <div class="pi-label">Último acceso</div>
                        <div class="pi-value"><?= timeAgo($user['last_login']) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="profile-info-item profile-info-card">
                        <div class="pi-label">Estado</div>
                        <div class="pi-value profile-status-pill">
                            <span class="profile-status-pill-dot"></span>
                            <span style="color: var(--success); text-shadow: 0 0 5px rgba(0,214,143,0.5);">Activo</span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($myTeam): ?>
            <div class="team-card card profile-surface profile-team-surface">
                <h3 class="card-title profile-surface-title profile-surface-title--small" style="margin-bottom:20px;">
                    EQUIPO ACTUAL
                </h3>
                
                <div class="profile-team-summary">
                    <?php
                        $logoPath = __DIR__ . '/../assets/uploads/teams/' . ($myTeam['logo'] ?? '');
                        $logoUrl = UPLOAD_URL . 'teams/' . ($myTeam['logo'] ?? '');
                        $defaultLogo = url('assets/img/default_team.png'); // Cambia la ruta si tu imagen por defecto está en otro lugar
                        if (!empty($myTeam['logo']) && file_exists($logoPath)) {
                    ?>
                        <img src="<?= $logoUrl ?>" class="profile-team-logo" alt="Logo de <?= htmlspecialchars($myTeam['team_name']) ?>">
                    <?php } else { ?>
                        <img src="<?= $defaultLogo ?>" class="profile-team-logo" alt="Logo por defecto del equipo">
                    <?php } ?>
                    <div>
                        <div class="profile-team-name"><?= htmlspecialchars($myTeam['team_name']) ?></div>
                        <div class="profile-team-meta">
                            <span class="badge badge-purple" style="font-size: 0.7rem; padding: 4px 8px;"><?= ML_ROLES[$myTeam['role']] ?? $myTeam['role'] ?></span>
                            <?= $myTeam['is_captain'] ? '<span class="badge badge-yellow" style="font-size: 0.7rem; padding: 4px 8px;">👑 Cap</span>' : '' ?>
                        </div>
                    </div>
                </div>
                
                <a href="<?= url('teams/view/' . $myTeam['team_id']) ?>" class="btn btn-secondary btn-block profile-team-link" style="font-size:0.95rem;">
                    Visitar Perfil del Equipo
                </a>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding: 40px 20px !important;">
                <h3 class="empty-state-title" style="font-size: 1.1rem !important;">Sin Equipo</h3>
                <p style="font-size: 0.9rem !important; margin-bottom: 20px;">Únete a un equipo competitivo para participar en torneos.</p>
                <a href="<?= url('teams') ?>" class="btn btn-secondary" style="padding: 10px 24px; font-size: 0.9rem;">Explorar Equipos</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Marco Selection Modal -->
<div id="marcoModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:500px; max-height:80vh; overflow-y:auto; padding:20px; position:relative;">
        <button type="button" data-close-modal style="position:absolute; top:16px; right:16px; background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">&times;</button>
        <h3 class="card-title" style="margin-bottom:20px; color: #fff; font-size:1.1rem;">Seleccionar Marco</h3>
        
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
            <div data-equip-marco-id="0" role="button" tabindex="0" style="cursor:pointer; padding:10px; border-radius:8px; background:<?= !$equippedMarco ? 'rgba(124,58,237,0.3)' : 'rgba(0,0,0,0.2)'; ?>; border:2px solid <?= !$equippedMarco ? 'var(--accent)' : 'transparent'; ?>; text-align:center;">
                <div style="width:60px; height:60px; margin:0 auto 8px; border:3px solid var(--text-muted); display:flex; align-items:center; justify-content:center; font-size:1.5rem; opacity: 0.5;">🚫</div>
                <div style="font-size:0.8rem; color:#fff;">Ninguno</div>
            </div>
            
            <?php foreach ($marcoItems as $item): ?>
            <div data-equip-marco-id="<?= $item['item_id'] ?>" role="button" tabindex="0" style="cursor:pointer; padding:10px; border-radius:8px; background:<?= ($equippedMarco && $equippedMarco['item_id'] == $item['item_id']) ? 'rgba(124,58,237,0.3)' : 'rgba(0,0,0,0.2)'; ?>; border:2px solid <?= ($equippedMarco && $equippedMarco['item_id'] == $item['item_id']) ? 'var(--accent)' : 'transparent'; ?>; text-align:center;">
                <?php if (!empty($item['image'])): ?>
                <div style="width:60px; height:60px; margin:0 auto 8px; padding:3px; background: url('<?= url('assets/shop/' . $item['image']) ?>') center/cover; border-radius:4px;">
                    <div style="width:100%; height:100%; background:var(--bg-elevated); display:flex; align-items:center; justify-content:center; font-size:1.5rem;"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                </div>
                <?php else: ?>
                <div style="width:60px; height:60px; margin:0 auto 8px; border:3px solid var(--accent-main); display:flex; align-items:center; justify-content:center; font-size:1.5rem;"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                <?php endif; ?>
                <div style="font-size:0.8rem; color:#fff;"><?= htmlspecialchars($item['name']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($marcoItems)): ?>
        <p style="text-align:center; color:var(--text-muted); margin-top:20px;">No tienes marcos en tu inventario.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Portada Selection Modal -->
<div id="portadaModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:500px; max-height:80vh; overflow-y:auto; padding:20px; position:relative;">
        <button type="button" data-close-modal style="position:absolute; top:16px; right:16px; background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">&times;</button>
        <h3 class="card-title" style="margin-bottom:20px; color: #fff; font-size:1.1rem;">Seleccionar Portada</h3>
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div data-equip-portada-id="0" role="button" tabindex="0" style="cursor:pointer; padding:10px; border-radius:8px; background:<?= !$equippedPortada ? 'rgba(124,58,237,0.3)' : 'rgba(0,0,0,0.2)'; ?>; border:2px solid <?= !$equippedPortada ? 'var(--accent)' : 'transparent'; ?>; text-align:center;">
                <div style="width:100%; height:80px; margin:0 auto 8px; background: var(--bg-elevated); border-radius:4px; display:flex; align-items:center; justify-content:center; opacity: 0.5;">🚫</div>
                <div style="font-size:0.8rem; color:#fff;">Ninguno</div>
            </div>
            
            <?php foreach ($portadaItems as $item): ?>
            <div data-equip-portada-id="<?= $item['item_id'] ?>" role="button" tabindex="0" style="cursor:pointer; padding:10px; border-radius:8px; background:<?= ($equippedPortada && $equippedPortada['item_id'] == $item['item_id']) ? 'rgba(124,58,237,0.3)' : 'rgba(0,0,0,0.2)'; ?>; border:2px solid <?= ($equippedPortada && $equippedPortada['item_id'] == $item['item_id']) ? 'var(--accent)' : 'transparent'; ?>; text-align:center;">
                <?php if (!empty($item['image'])): ?>
                <div style="width:100%; height:80px; margin:0 auto 8px; background: url('<?= url('assets/shop/' . $item['image']) ?>') center/cover; border-radius:4px;"></div>
                <?php else: ?>
                <div style="width:100%; height:80px; margin:0 auto 8px; background: var(--bg-elevated); border-radius:4px;"></div>
                <?php endif; ?>
                <div style="font-size:0.8rem; color:#fff;"><?= htmlspecialchars($item['name']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($portadaItems)): ?>
        <p style="text-align:center; color:var(--text-muted); margin-top:20px;">No tienes portadas en tu inventario.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Email Change Modal -->
<div id="emailModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:450px; padding:0; position:relative;">
        <button type="button" data-close-email-modal style="position:absolute; top:16px; right:16px; background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">&times;</button>
        <h3 class="card-title" style="margin:0; color: #fff; font-size:1.1rem;">Cambiar Email</h3>
        
        <div id="emailStep1" style="padding:20px;">
            <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom:20px;">Ingresa tu nuevo correo electrónico y te enviaremos un código de verificación.</p>
            <form method="POST" action="<?= url('profile/send-email-code') ?>">
                <?= csrfField() ?>
                <div class="form-group">
                    <label class="form-label">Nuevo Email</label>
                    <input type="email" name="new_email" class="form-control" required style="height: 48px;">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%; margin-top:16px; padding:14px;">📧 Enviar Código</button>
            </form>
        </div>
        
        <div id="emailStep2" style="display:none; padding:20px;">
            <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom:20px;">Hemos enviado un código de 6 dígitos a <strong id="pendingEmail"></strong>. Ingresa el código para verificar.</p>
            <form method="POST" action="<?= url('profile/verify-email-code') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="new_email" id="verifyEmailInput">
                <div class="form-group">
                    <label class="form-label">Código de Verificación</label>
                    <input type="text" name="code" class="form-control" maxlength="6" pattern="[0-9]{6}" required style="height: 48px; font-size:1.5rem; text-align:center; letter-spacing:8px;" placeholder="000000">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%; margin-top:16px; padding:14px;">✅ Verificar y Cambiar</button>
            </form>
            <button type="button" data-resend-email class="btn btn-secondary" style="width:100%; margin-top:10px; padding:12px;">📨 Reenviar Código</button>
        </div>
        
        <div id="emailSuccess" style="display:none; text-align:center; padding:20px 0;">
            <div style="font-size:3rem; margin-bottom:16px;">✅</div>
            <h4 style="margin-bottom:8px;">Email actualizado!</h4>
            <p style="font-size:0.9rem; color:var(--text-muted);">Tu correo ha sido cambiado exitosamente.</p>
            <button type="button" data-close-email-modal class="btn btn-primary" style="width:100%; margin-top:16px; padding:14px;">Aceptar</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
