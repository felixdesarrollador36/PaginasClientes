<?php
$pageTitle = 'Crear Equipo';
$page = 'teams';
$pageCss = 'team-create';
// Check if already in a team
$teamCtrl = new TeamController();
$myTeam = $teamCtrl->getUserTeam(currentUserId());
if ($myTeam) { setFlash('warning', 'Ya perteneces a un equipo.'); redirect('teams'); }

// Load heroes from DB (unified with admin panel)
$db = Database::getInstance();
$_allHeroes = $db->fetchAll("SELECT id, name, role, image_url FROM ml_heroes ORDER BY name ASC");
// Map Spanish role names from DB to system role keys
$_roleMap = [
    'Tirador' => 'adc', 'TIRADOR' => 'adc',
    'Mago' => 'mage',   'MAGO' => 'mage',
    'Tanque' => 'tank', 'TANQUE' => 'tank', 'Tanque/Apoyo' => 'tank', 'Tanque/Combatiente' => 'tank',
    'Asesino' => 'assassin', 'ASESINO' => 'assassin',
    'Combatiente' => 'fighter', 'COMBATIENTE' => 'fighter',
    'Apoyo' => 'support', 'APOYO' => 'support',
];
$heroesData = [];
foreach ($_allHeroes as $h) {
    // Detect role key from primary role (before /)
    $primaryRole = explode('/', $h['role'])[0];
    $roleKey = $_roleMap[$primaryRole] ?? $_roleMap[$h['role']] ?? null;
    if (!$roleKey) continue;
    $heroesData[$roleKey][] = [
        'name' => $h['name'],
        'img'  => $h['image_url'] ? BASE_URL . ltrim($h['image_url'], '/') : null,
        'role' => $roleKey,
    ];
}

$currentUserProfile = $db->fetch("SELECT username, ml_id, ml_server, ml_nickname FROM users WHERE id = ?", [currentUserId()]) ?: [];
$profileMlId = trim((string)($currentUserProfile['ml_id'] ?? ''));
$profileMlServer = trim((string)($currentUserProfile['ml_server'] ?? ''));
$profileMlNickname = trim((string)($currentUserProfile['ml_nickname'] ?? ''));
$profileUsername = trim((string)($currentUserProfile['username'] ?? currentUsername() ?? ''));

$mlIdLocked = $profileMlId !== '';
$mlServerLocked = $profileMlServer !== '';
$mlNicknameLocked = $profileMlNickname !== '';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content">
    <a href="<?= url('teams') ?>" class="team-create-back-link">← Volver</a>
    <div class="page-header"><h1 class="page-title">➕ Crear Equipo</h1></div>

    <div class="card team-create-card">
        <form method="POST" action="<?= url('teams/create') ?>" enctype="multipart/form-data" id="teamForm">
            <?= csrfField() ?>

            <h3 class="section-title-fancy">📋 Información del Equipo</h3>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Nombre del Equipo *</label>
                    <input type="text" name="name" class="form-control" placeholder="Los Invencibles" required minlength="3">
                </div>
                <div class="form-group">
                    <label class="form-label">Tag (abreviación)</label>
                    <input type="text" name="tag" class="form-control" placeholder="INV" maxlength="10">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Descripción</label>
                <textarea name="description" class="form-control" placeholder="Describe tu equipo..." rows="3"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Región</label>
                    <input type="text" name="region" class="form-control" placeholder="Santo Domingo, RD">
                </div>
                <div class="form-group">
                    <label class="form-label">Logo del Equipo</label>
                    <input type="file" name="logo" class="form-control" accept="image/*">
                </div>
            </div>

            <hr class="esports-divider">
            <h3 class="section-title-fancy">🎮 Tu Perfil de Jugador (Capitán)</h3>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">ID de Mobile Legends *</label>
                    <input type="text" name="ml_id" class="form-control<?= $mlIdLocked ? ' team-create-readonly' : '' ?>" value="<?= htmlspecialchars($profileMlId) ?>" placeholder="Tu ID de Mobile Legends" required <?= $mlIdLocked ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label class="form-label">Server *</label>
                    <input type="text" name="ml_server" class="form-control<?= $mlServerLocked ? ' team-create-readonly' : '' ?>" value="<?= htmlspecialchars($profileMlServer) ?>" placeholder="Tu server" inputmode="numeric" pattern="[0-9]+" required <?= $mlServerLocked ? 'readonly' : '' ?>>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Nick en el Juego *</label>
                    <input type="text" name="ml_nickname" class="form-control<?= $mlNicknameLocked ? ' team-create-readonly' : '' ?>" value="<?= htmlspecialchars($profileMlNickname) ?>" placeholder="Tu nickname en ML" required <?= $mlNicknameLocked ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label class="form-label">Apodo</label>
                    <input type="text" name="nickname" class="form-control" value="<?= htmlspecialchars($profileUsername) ?>" placeholder="Tu apodo">
                </div>
            </div>

            <!-- ========== VISUAL ROLE SELECTOR ========== -->
            <div class="form-group">
                <label class="form-label">Rol Principal *</label>
                <input type="hidden" name="role" id="roleInput" required>
                <div class="visual-role-grid">
                    <?php foreach (ML_ROLES as $k => $v): ?>
                    <div class="role-card" data-role="<?= $k ?>" data-select-role role="button" tabindex="0">
                        <img src="<?= asset('ROL/' . ML_ROLE_IMAGES[$k]) ?>" alt="<?= $v ?>" class="role-card-img">
                        <div class="role-card-label"><?= $v ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ========== VISUAL RANK SELECTOR ========== -->
            <div class="form-group">
                <label class="form-label">Rango Actual</label>
                <input type="hidden" name="current_rank" id="rankInput">
                <div class="visual-rank-grid">
                    <?php foreach (ML_RANKS as $k => $v): ?>
                    <div class="rank-card" data-rank="<?= $k ?>" data-select-rank role="button" tabindex="0">
                        <img src="<?= asset('RANGOS_IMG/' . ML_RANK_IMAGES[$k]) ?>" alt="<?= $v ?>" class="rank-card-img">
                        <div class="rank-card-label"><?= $v ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ========== VISUAL LANE SELECTORS ========== -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Línea Principal *</label>
                    <input type="hidden" name="lane_1" id="lane1Input" required>
                    <div class="visual-lane-grid" id="lane1Grid">
                        <?php foreach (ML_LANES as $k => $v): ?>
                        <div class="lane-card" data-lane="<?= $k ?>" data-lane-group="1" data-select-lane role="button" tabindex="0">
                            <img src="<?= asset('LINEAS/' . ML_LANE_IMAGES[$k]) ?>" alt="<?= $v ?>" class="lane-card-img">
                            <div class="lane-card-label"><?= $v ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Línea Secundaria</label>
                    <input type="hidden" name="lane_2" id="lane2Input">
                    <div class="visual-lane-grid" id="lane2Grid">
                        <div class="lane-card selected" data-lane="" data-lane-group="2" data-select-lane role="button" tabindex="0">
                            <div class="lane-card-icon lane-card-icon--empty">❌</div>
                            <div class="lane-card-label">Ninguna</div>
                        </div>
                        <?php foreach (ML_LANES as $k => $v): ?>
                        <div class="lane-card" data-lane="<?= $k ?>" data-lane-group="2" data-select-lane role="button" tabindex="0">
                            <img src="<?= asset('LINEAS/' . ML_LANE_IMAGES[$k]) ?>" alt="<?= $v ?>" class="lane-card-img">
                            <div class="lane-card-label"><?= $v ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- ========== VISUAL HERO PICKER ========== -->
            <div class="form-group">
                <label class="form-label">Main Principal (Héroe) *</label>
                <input type="hidden" name="main_hero" id="heroInput" required>
                
                <div class="hero-picker-selected is-hidden" id="heroSelectedDisplay">
                    <img id="heroSelectedImg" src="" alt="">
                    <span id="heroSelectedName"></span>
                    <button type="button" class="btn btn-sm btn-secondary" data-open-hero-picker>Cambiar</button>
                </div>
                <button type="button" class="btn btn-secondary btn-block team-create-hero-trigger" id="heroPickerBtn" data-open-hero-picker>
                    🦸 Seleccionar Héroe Principal
                </button>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block team-create-submit">🚀 Crear Equipo</button>
        </form>
    </div>

<!-- Hero Picker Modal -->
<div class="hero-modal-overlay" id="heroModal">
    <div class="hero-modal">
        <div class="hero-modal-header">
            <h3>🦸 Seleccionar Héroe Principal</h3>
            <button type="button" class="hero-modal-close" data-close-hero-picker>&times;</button>
        </div>
        <div class="hero-modal-search">
            <input type="text" id="heroSearch" class="form-control" placeholder="🔍 Buscar héroe...">
        </div>
        <div class="hero-modal-tabs">
            <button class="hero-tab active" data-filter-role="all">Todos</button>
            <?php foreach (ML_ROLES as $k => $v): ?>
            <button class="hero-tab" data-filter-role="<?= $k ?>"><?= $roleIcons[$k] ?? '' ?> <?= explode(' ', $v)[0] ?></button>
            <?php endforeach; ?>
        </div>
        <div class="hero-modal-grid" id="heroGrid">
            <?php foreach ($heroesData as $roleKey => $heroes): ?>
                <?php foreach ($heroes as $h): ?>
                <?php $imgSrc = $h['img'] ? htmlspecialchars($h['img']) : asset('heroes_img/placeholder.png'); ?>
                <div class="hero-pick-card" data-role="<?= $roleKey ?>" data-name="<?= strtolower($h['name']) ?>" data-hero-name="<?= htmlspecialchars($h['name']) ?>" data-hero-img="<?= $imgSrc ?>" role="button" tabindex="0">
                    <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($h['name']) ?>" loading="lazy" data-fallback-src="<?= asset('heroes_img/placeholder.png') ?>">
                    <span><?= htmlspecialchars($h['name']) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
