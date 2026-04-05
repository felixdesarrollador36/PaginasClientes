<?php
$pageTitle = 'Recuperar Contraseña';
$pageCss = 'forgot-password';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-shape"></div>
<div class="wrap">
  <div class="card">
    <div class="icon">🔐</div>
    <h1 class="title">Recuperar <span class="title-accent">Contraseña</span></h1>
    <p class="sub">Ingresa tu correo y te enviaremos un código de <strong class="sub-accent">6 dígitos</strong> para restablecerla.</p>

    <form method="POST" action="<?= url('forgot-password') ?>">
      <?= csrfField() ?>
      <div class="field">
        <label class="label" for="email">Correo electrónico</label>
        <input type="email" name="email" id="email" class="inp" placeholder="tu@correo.com" required>
      </div>
      <button type="submit" class="btn">Enviar código →</button>
    </form>

    <a href="<?= url('login') ?>" class="back">← Volver al inicio de sesión</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
