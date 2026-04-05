<?php
$pageTitle = 'Completar Perfil';
$page = 'complete-profile';

require_once __DIR__ . '/../includes/header.php';

// We don't include the navbar to force focus on completion
?>
<div class="complete-profile-shell">
    <div class="complete-profile-wrap">
        <div class="card auth-card complete-profile-card">
            <div class="complete-profile-head">
                <img src="<?= url('assets/img/logo.png') ?>" alt="<?= SITE_NAME ?>" class="complete-profile-logo">
                <h1 class="complete-profile-title">¡Actualización Requerida!</h1>
                <p class="complete-profile-desc">Hemos añadido nuevos campos esenciales a <?= SITE_NAME ?>. Para continuar navegando y participando en torneos, por favor completa esta información.</p>
            </div>

            <form method="POST" action="<?= url('complete-profile') ?>">
                <?= csrfField() ?>

                <div class="form-group">
                    <label class="form-label complete-profile-label">
                        <svg class="icon-svg complete-profile-icon complete-profile-icon--whatsapp" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
                        Número de WhatsApp
                    </label>
                    <input type="text" name="whatsapp" class="form-control complete-profile-input" placeholder="+1234567890" required>
                </div>

                <div class="form-group">
                    <label class="form-label complete-profile-label">
                        <svg class="icon-svg complete-profile-icon complete-profile-icon--device" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                        Marca/Modelo de Celular
                    </label>
                    <input type="text" name="phone_brand" class="form-control complete-profile-input" placeholder="Ej: iPhone 13, Samsung Galaxy S22..." required>
                </div>

                <div class="form-group complete-profile-discord-group">
                    <label class="form-label complete-profile-label">
                        <svg class="icon-svg complete-profile-icon complete-profile-icon--discord" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.16 4.7a16.82 16.82 0 00-4.14-1.28 1 1 0 00-.73.23l-.3.34a12.87 12.87 0 00-4 0l-.3-.34a1 1 0 00-.72-.23A16.82 16.82 0 003.84 4.7a1 1 0 00-.4.32C1.5 8.4.53 12.38.83 16.35a1 1 0 00.31.7 17.06 17.06 0 004.81 2.5 1 1 0 00.9-.17l1-1.12a1 1 0 00-.23-1.4 12.28 12.28 0 01-1.57-.8 1 1 0 01.12-1.63L6 14.12a1 1 0 01.95 0 12.4 12.4 0 0010.1 0 1 1 0 01.95 0l.18.1a1 1 0 01.13 1.63 12.28 12.28 0 01-1.58.8 1 1 0 00-.23 1.4l1 1.12a1 1 0 00.9.17 17.06 17.06 0 004.8-2.5 1 1 0 00.32-.7c.36-4.52-.94-8.38-2.61-11.34a1 1 0 00-.4-.3zM8.5 12.5C7.67 12.5 7 11.75 7 10.83c0-.92.67-1.67 1.5-1.67.84 0 1.5.75 1.5 1.67 0 .92-.66 1.67-1.5 1.67zm7 0c-.84 0-1.5-.75-1.5-1.67 0-.92.67-1.67 1.5-1.67.84 0 1.5.75 1.5 1.67 0 .92-.66 1.67-1.5 1.67z"/></svg>
                        Discord ID
                    </label>
                    <input type="text" name="discord" class="form-control complete-profile-input" placeholder="Ej: usuario#1234 o usuario" required>
                    <small class="complete-profile-note">Necesario para la comunicación en torneos.</small>
                </div>

                <div class="complete-profile-actions">
                    <button type="submit" class="btn btn-primary complete-profile-save">✓ Guardar y Continuar</button>
                    <a href="<?= url('logout') ?>" class="btn btn-secondary complete-profile-logout">Cerrar Sesión</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
