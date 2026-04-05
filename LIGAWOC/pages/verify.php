<?php
$pageTitle = 'Verificar Cuenta';
$pageCss = 'verify';
require_once __DIR__ . '/../includes/header.php';
$prefilledEmail = htmlspecialchars($_SESSION['pending_verify_email'] ?? '');
?>

<div class="verify-bg"></div>

<div class="verify-wrap">
  <div class="verify-card">
    <div class="verify-icon">📬</div>
    <h1 class="verify-title">Verificar <span class="accent">Cuenta</span></h1>
    <p class="verify-sub">
      Ingresa el código de <strong class="verify-sub-accent">6 dígitos</strong> que enviamos a tu correo electrónico.<br>
      <small class="verify-sub-note">Revisa también la carpeta de SPAM.</small>
    </p>

    <!-- Verification Form -->
    <form id="verifyForm" method="POST" action="<?= url('verify') ?>">
      <?= csrfField() ?>
      
      <div class="email-field">
        <label class="email-label" for="emailInput">Tu correo electrónico</label>
        <input type="email" name="email" id="emailInput" class="email-input"
          value="<?= $prefilledEmail ?>"
          placeholder="tu@correo.com" required>
      </div>

      <!-- 6 OTP boxes -->
      <div class="otp-row">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <input type="number" class="otp-box" maxlength="1" min="0" max="9"
            inputmode="numeric" autocomplete="one-time-code"
            id="otp<?= $i ?>" data-index="<?= $i ?>">
        <?php endfor; ?>
      </div>

      <!-- Hidden full OTP input sent to server -->
      <input type="hidden" name="code" id="otpHidden">

      <button type="submit" class="btn-verify" id="verifyBtn">VERIFICAR CUENTA →</button>
    </form>

    <div class="divider">¿No recibiste el código?</div>

    <!-- Resend form -->
    <form class="resend-form" method="POST" action="<?= url('verify/resend') ?>">
      <?= csrfField() ?>
      <input type="hidden" name="email" id="resendEmail" value="<?= $prefilledEmail ?>">
      <button type="submit" class="btn-resend">📨 Reenviar código</button>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
