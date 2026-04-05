<?php
/**
 * Liga WOC — Login
 */
if (isLoggedIn()) redirect('dashboard');
$pageTitle = 'Iniciar Sesión';
$pageCss = 'login';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo">
            <img src="<?= asset('img/logo.png') ?>" alt="Liga WOC Logo" class="login-logo-image">
            <div class="auth-logo-text">Liga WOC</div>
            <div class="auth-logo-sub">Plataforma de Torneos Mobile Legends</div>
        </div>

        <form method="POST" action="<?= url('login') ?>" autocomplete="on">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label">Usuario o Email</label>
                <input type="text" name="login" class="form-control" placeholder="Ingresa tu usuario o email" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label login-password-label">
                    <span>Contraseña</span>
                    <a href="<?= url('forgot-password') ?>" class="login-forgot-link">Recuperar</a>
                </label>
                <input type="password" name="password" class="form-control" placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;" required>
            </div>

            <div class="login-remember-row">
                <input type="checkbox" name="remember" id="remember" class="login-remember-checkbox">
                <label for="remember" class="login-remember-label">Recordarme</label>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">
                Iniciar Sesión
            </button>
        </form>

        <div class="auth-footer">
            ¿No tienes cuenta? <a href="<?= url('register') ?>">Regístrate aquí</a>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
