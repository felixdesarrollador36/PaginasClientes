<?php
$pageTitle = 'Notificaciones';
$page = 'notifications';
$pageCss = 'notifications';
$notifCtrl = new NotificationController();
$notifications = $notifCtrl->getUserNotifications(currentUserId(), 50);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div
    class="main-content notifications-page"
    data-mark-all-read-url="<?= htmlspecialchars(url('api/notifications/mark-read/all'), ENT_QUOTES) ?>"
    data-csrf-token="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>"
>
    <div class="page-header notifications-header">
        <div>
            <h1 class="page-title">🔔 Notificaciones</h1>
            <p class="page-subtitle">Mantente al día con tus actividades</p>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" data-mark-all-read>✅ Marcar todas como leídas</button>
    </div>

    <?php if (!empty($notifications)): ?>
    <div class="card">
        <?php foreach ($notifications as $notif):
            $icons = ['match' => '⚔️', 'team' => '👥', 'tournament' => '🏆', 'news' => '📰', 'system' => '⚙️', 'prize' => '🎁'];
        ?>
        <div class="notifications-item<?= $notif['is_read'] ? ' notifications-item--read' : '' ?>">
            <div class="notifications-item-icon"><?= $icons[$notif['type']] ?? '📬' ?></div>
            <div class="notifications-item-body">
                <div class="notifications-item-title"><?= htmlspecialchars($notif['title']) ?></div>
                <div class="notifications-item-message"><?= htmlspecialchars($notif['message']) ?></div>
                <div class="notifications-item-time"><?= timeAgo($notif['created_at']) ?></div>
            </div>
            <?php if ($notif['link']): ?>
            <a href="<?= url($notif['link']) ?>" class="btn btn-sm btn-secondary notification-view-btn" data-id="<?= $notif['id'] ?>">Ver</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <div class="empty-state-icon">🔔</div>
        <h3 class="empty-state-title">No hay notificaciones</h3>
        <p>Aquí aparecerán tus notificaciones</p>
    </div>
    <?php endif; ?>

</div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
