<?php
/**
 * Liga WOC — Registro Interactivo PREMIUM
 */
if (isLoggedIn()) redirect('dashboard');

require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();
$ml_heroes = $db->fetchAll("SELECT * FROM ml_heroes ORDER BY name ASC");

$pageTitle = 'Crear Cuenta';
$pageCss = 'register';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- BACKGROUND -->
<div class="reg-bg">
  <div class="reg-grid"></div>
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
</div>

<div class="registration-wrapper">
  <form id="regForm" class="reg-form" method="POST" action="<?= url('register') ?>">
    <?= csrfField() ?>
    <input type="hidden" name="main_hero"    id="hiddenHero" value="">
    <input type="hidden" name="current_rank" id="hiddenRank" value="">

    <!-- ══════════════════════════════════════ STEP 0 — WELCOME -->
    <div class="step-container active" id="step0">
      <div class="scan-line"></div>
      <div class="welcome-logo">
        <div class="logo-ring">
          <img src="<?= asset('img/logo.png') ?>" alt="Liga WOC">
        </div>
        <h1 class="welcome-title">LIGA WOC</h1>
        <p class="welcome-sub">La plataforma definitiva de Mobile Legends competitivo</p>
      </div>
      <div class="welcome-cta-wrap">
        <button type="button" class="btn-reg btn-cta btn-full" data-step-action="next" data-step-target="1">CREAR CUENTA</button>
        <div class="divider-line">o</div>
        <a href="<?= url('login') ?>" class="link-login">¿Ya tienes cuenta? <span>Inicia sesión</span></a>
      </div>
    </div>

    <!-- ══════════════════════════════════════ STEP 1 — EMAIL & CONTACT -->
    <div class="step-container" id="step1">
      <div class="progress-bar-wrap">
        <div class="progress-seg active"></div><div class="progress-seg"></div><div class="progress-seg"></div><div class="progress-seg"></div>
      </div>
      <div class="step-header">
        <p class="step-eyebrow">Paso 1 de 4</p>
        <h2 class="step-title">Tus <span class="accent">Datos</span></h2>
        <p class="step-subtitle">Información de contacto para tu perfil</p>
      </div>
      <div class="field-row">
          <div class="field">
            <label class="field-label" for="email">Correo Electrónico *</label>
            <input type="email" name="email" id="email" class="field-input" placeholder="tu@email.com" required autocomplete="email">
          </div>
          <div class="field">
            <label class="field-label" for="whatsapp">WhatsApp *</label>
            <input type="tel" name="whatsapp" id="whatsapp" class="field-input" placeholder="+1234567890" required>
          </div>
      </div>
      <div class="field-row">
          <div class="field">
            <label class="field-label" for="discord">Usuario de Discord *</label>
            <input type="text" name="discord" id="discord" class="field-input" placeholder="Usuario#1234 o nombre" required>
          </div>
          <div class="field">
            <label class="field-label" for="phone_brand">Marca de Celular *</label>
            <input type="text" name="phone_brand" id="phone_brand" class="field-input" placeholder="Ej. iPhone, Samsung, Xiaomi" required>
          </div>
      </div>
      <div class="step-actions">
        <button type="button" class="btn-reg btn-ghost" data-step-action="prev" data-step-target="0">← Volver</button>
        <button type="button" class="btn-reg btn-primary" data-step-action="validate-next" data-step-from="1" data-step-to="2">Siguiente →</button>
      </div>
    </div>

    <!-- ══════════════════════════════════════ STEP 2 — USERNAME -->
    <div class="step-container" id="step2">
      <div class="progress-bar-wrap">
        <div class="progress-seg done"></div><div class="progress-seg active"></div><div class="progress-seg"></div><div class="progress-seg"></div>
      </div>
      <div class="step-header">
        <p class="step-eyebrow">Paso 2 de 4</p>
        <h2 class="step-title">Tu <span class="accent">Nickname</span></h2>
        <p class="step-subtitle">Elige cómo te verán los demás en la plataforma</p>
      </div>
      <div class="field">
        <label class="field-label" for="username">Nombre de usuario</label>
        <input type="text" name="username" id="username" class="field-input" placeholder="Ej. Faker" required minlength="3" maxlength="20" pattern="[A-Za-z0-9]+" title="Solo letras y números, sin espacios ni guiones" autocomplete="username" spellcheck="false">
      </div>
      <div class="step-actions">
        <button type="button" class="btn-reg btn-ghost" data-step-action="prev" data-step-target="1">← Volver</button>
        <button type="button" class="btn-reg btn-primary" data-step-action="validate-next" data-step-from="2" data-step-to="3">Siguiente →</button>
      </div>
    </div>

    <!-- ══════════════════════════════════════ STEP 3 — PASSWORD -->
    <div class="step-container" id="step3">
      <div class="progress-bar-wrap">
        <div class="progress-seg done"></div><div class="progress-seg done"></div><div class="progress-seg active"></div><div class="progress-seg"></div>
      </div>
      <div class="step-header">
        <p class="step-eyebrow">Paso 3 de 4</p>
        <h2 class="step-title">Tu <span class="accent">Seguridad</span></h2>
        <p class="step-subtitle">Protege tu cuenta con una contraseña fuerte</p>
      </div>
      <div class="field">
        <label class="field-label" for="password">Contraseña</label>
        <input type="password" name="password" id="password" class="field-input" placeholder="Exactamente 9 caracteres" required minlength="9" maxlength="9" autocomplete="new-password">
      </div>
      <div class="field">
        <label class="field-label" for="password_confirm">Confirmar contraseña</label>
        <input type="password" name="password_confirm" id="password_confirm" class="field-input" placeholder="Repite tu contraseña" required minlength="9" maxlength="9" autocomplete="new-password">
      </div>
      <div class="step-actions">
        <button type="button" class="btn-reg btn-ghost" data-step-action="prev" data-step-target="2">← Volver</button>
        <button type="button" class="btn-reg btn-primary" data-step-action="validate-next" data-step-from="3" data-step-to="4">Siguiente →</button>
      </div>
    </div>

    <!-- ══════════════════════════════════════ STEP 4 — MLBB -->
    <div class="step-container" id="step4">
      <div class="progress-bar-wrap">
        <div class="progress-seg done"></div><div class="progress-seg done"></div><div class="progress-seg done"></div><div class="progress-seg active"></div>
      </div>
      <div class="step-header">
        <p class="step-eyebrow">Paso 4 de 4</p>
        <h2 class="step-title">Enlazar <span class="accent">MLBB</span></h2>
        <p class="step-subtitle">Conecta tu cuenta de Mobile Legends (Obligatorio)</p>
      </div>
      <div class="field-row">
        <div class="field">
          <label class="field-label" for="ml_id">ID de ML</label>
          <input type="text" name="ml_id" id="ml_id" class="field-input" placeholder="123456789" inputmode="numeric" pattern="[0-9]+" title="Solo se permiten numeros" required>
        </div>
        <div class="field">
          <label class="field-label" for="ml_server">Servidor</label>
          <input type="text" name="ml_server" id="ml_server" class="field-input" placeholder="Ej. 1234" inputmode="numeric" pattern="[0-9]+" title="Solo se permiten números" required>
        </div>
      </div>
      <div class="field">
        <label class="field-label" for="ml_nickname">Nickname en el juego</label>
        <input type="text" name="ml_nickname" id="ml_nickname" class="field-input" placeholder="Exacto como aparece en el juego" required spellcheck="false">
      </div>
      <div class="step-actions">
        <button type="button" class="btn-reg btn-ghost" data-step-action="prev" data-step-target="3">← Volver</button>
        <button type="button" class="btn-reg btn-primary" data-step-action="validate-next" data-step-from="4" data-step-to="5">Siguiente →</button>
      </div>
    </div>

    <!-- ══════════════════════════════════════ STEP 5 — HERO & RANK (EPIC) -->
    <div class="step-container" id="step5">
      <div class="step-header step-header-compact">
        <p class="step-eyebrow">Últimos detalles</p>
        <h2 class="step-title step-title-small">Estilo de <span class="accent">Juego</span></h2>
        <p class="step-subtitle">Elige tu héroe — la elección que te define en la batalla</p>
      </div>

      <div class="hero-arena">

        <!-- ── LEFT: STAGE ── -->
        <div class="hero-stage" id="heroStageWrap">
          <div class="stage-scan"></div>
          <div class="stage-corner stage-corner-tl"></div>
          <div class="stage-corner stage-corner-tr"></div>
          <div class="stage-corner stage-corner-bl"></div>
          <div class="stage-corner stage-corner-br"></div>

          <!-- Idle -->
          <div class="stage-idle" id="stageIdle">
            <div class="idle-orb">
              <span class="idle-sword">⚔</span>
            </div>
            <div class="idle-text">Tu héroe</div>
            <div class="idle-subtext">Selecciona un héroe del panel</div>
          </div>

          <!-- Selected -->
          <div class="stage-hero" id="stageHero">
            <div class="stage-bg-art" id="stageBgArt"></div>
            <div class="stage-mask-bottom"></div>
            <div class="stage-mask-vignette"></div>

            <div class="stage-rings">
              <div class="stage-ring"></div>
              <div class="stage-ring"></div>
              <div class="stage-ring"></div>
            </div>
            <div class="stage-ground-glow"></div>

            <div class="stage-avatar-wrap">
              <img src="" class="stage-avatar" id="stageAvatar" alt="Hero">
            </div>
            <div class="stage-name-block">
              <div class="stage-hero-name" id="stageName">NAME</div>
              <div class="stage-hero-role" id="stageRole">HERO</div>
            </div>

            <canvas id="pCanvas"></canvas>
          </div>
        </div>

        <!-- ── RIGHT PANEL ── -->
        <div class="hero-right">

          <!-- Search -->
          <div class="search-wrap search-wrap-tight">
            <span class="search-ico">🔍</span>
            <input type="text" id="heroSearch" class="search-input" placeholder="Buscar héroe...">
          </div>

          <!-- Hero grid -->
          <div class="hero-grid" id="gridHero">
            <?php foreach($ml_heroes as $h): ?>
            <div class="hcard hero-item" data-val="<?= htmlspecialchars($h['name']) ?>" data-img="<?= url($h['image_url']) ?>">
              <div class="hcard-img-wrap"><img src="<?= url($h['image_url']) ?>" loading="lazy" alt="Hero"></div>
              <div class="hcard-name"><?= htmlspecialchars($h['name']) ?></div>
              <div class="hcard-check">✓</div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Ranks -->
          <div class="rank-section">
            <div class="rank-label">Rango Actual</div>
            <div class="rank-grid" id="gridRank">
              <?php foreach(ML_RANKS as $key => $name): ?>
              <div class="rcard rank-item" data-val="<?= $key ?>">
                <img src="<?= asset('RANGOS_IMG/' . (ML_RANK_IMAGES[$key] ?? 'default.png')) ?>" loading="lazy" alt="<?= $name ?>">
                <span><?= $name ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

        </div>
      </div><!-- /hero-arena -->

      <div class="step-actions">
        <button type="button" class="btn-reg btn-ghost" data-step-action="prev" data-step-target="4">← Volver</button>
        <button type="submit" class="btn-reg btn-cta" id="finalSubmitBtn">¡REGISTRARME! →</button>
      </div>
    </div><!-- /step5 -->

  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>