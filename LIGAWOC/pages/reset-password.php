<?php
$pageTitle = 'Nueva Contraseña';
$pageCss = 'reset-password';
require_once __DIR__ . '/../includes/header.php';
$prefilledEmail = htmlspecialchars($_SESSION['pending_reset_email'] ?? '');
?>

<div class="bg-shape"></div>
<div class="wrap">
  <div class="card">
    <div class="icon">🔑</div>
    <h1 class="title">Nueva <span class="title-accent">Contraseña</span></h1>
    <p class="sub">Ingresa el código de 6 dígitos que te enviamos y elige tu nueva contraseña.</p>

    <form method="POST" action="<?= url('reset-password') ?>" id="resetForm">
      <?= csrfField() ?>

      <div class="field">
        <label class="label">Tu correo electrónico</label>
        <input type="email" name="email" class="inp" value="<?= $prefilledEmail ?>" placeholder="tu@correo.com" required>
      </div>

      <hr class="divider">
      <label class="label otp-label">Código de verificación</label>
      <div class="otp-row">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <input type="number" class="otp-box" min="0" max="9" inputmode="numeric" id="r<?=$i?>" data-index="<?=$i?>">
        <?php endfor; ?>
      </div>
      <input type="hidden" name="code" id="codeHidden">
      <hr class="divider">

      <div class="field">
        <label class="label">Nueva contraseña</label>
        <input type="password" name="password" class="inp" id="newPass" placeholder="Mínimo 6 caracteres" required minlength="6">
      </div>
      <div class="field">
        <label class="label">Confirmar contraseña</label>
        <input type="password" name="password_confirm" class="inp" id="newPass2" placeholder="Repite la contraseña" required>
      </div>

      <button type="submit" class="btn">Guardar nueva contraseña →</button>
    </form>

    <a href="<?= url('forgot-password') ?>" class="back">← Solicitar un nuevo código</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
