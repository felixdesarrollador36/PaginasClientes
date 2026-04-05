<?php
$pageTitle = 'Transmisión en Vivo';
$page = 'stream';
$db = Database::getInstance();
$streams = $db->fetchAll("SELECT s.*, t.name as tournament_name FROM streams s LEFT JOIN tournaments t ON s.tournament_id = t.id ORDER BY s.is_live DESC, s.scheduled_at DESC LIMIT 10");

/**
 * Detect and generate an embed URL from a Twitch or YouTube stream URL.
 * Returns ['type' => 'twitch'|'youtube'|null, 'embed_url' => string|null]
 */
function getStreamEmbed(string $url): array {
    // YouTube: youtube.com/watch?v=ID or youtu.be/ID
    if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $url, $m) ||
        preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $m)) {
        return ['type' => 'youtube', 'embed_url' => 'https://www.youtube.com/embed/' . $m[1] . '?autoplay=0&rel=0'];
    }
    // YouTube Live channel
    if (preg_match('/youtube\.com\/channel\/([a-zA-Z0-9_-]+)\/live/', $url, $m)) {
        return ['type' => 'youtube', 'embed_url' => 'https://www.youtube.com/embed/live_stream?channel=' . $m[1]];
    }
    // Twitch: twitch.tv/channelname
    if (preg_match('/twitch\.tv\/([a-zA-Z0-9_]+)/', $url, $m)) {
        $parent = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return ['type' => 'twitch', 'embed_url' => 'https://player.twitch.tv/?channel=' . $m[1] . '&parent=' . $parent . '&autoplay=false'];
    }
    return ['type' => null, 'embed_url' => null];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="app-wrapper">
<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">📺 Transmisiones</h1>
        <p class="page-subtitle">Mira las partidas en vivo y grabaciones</p>
    </div>

    <?php if (!empty($streams)): ?>
        <?php foreach ($streams as $stream):
            $embed = getStreamEmbed($stream['url']);
        ?>
        <div class="card mb-3<?= $stream['is_live'] ? ' stream-card-live' : '' ?>">
            <div class="stream-card-header <?= ($stream['is_live'] && $embed['embed_url']) ? 'stream-card-header--spaced' : 'stream-card-header--compact' ?>">
                <div>
                    <?php if ($stream['is_live']): ?>
                        <span class="live-indicator stream-live-indicator"><span class="live-dot"></span> EN VIVO</span>
                    <?php endif; ?>
                    <h3 class="stream-card-title"><?= htmlspecialchars($stream['title']) ?></h3>
                    <div class="stream-card-meta">
                        <span class="badge badge-<?= $embed['type'] === 'twitch' ? 'purple' : 'blue' ?>"><?= ucfirst($embed['type'] ?? $stream['platform']) ?></span>
                        <?php if ($stream['tournament_name']): ?><span class="stream-card-meta-item">🏆 <?= htmlspecialchars($stream['tournament_name']) ?></span><?php endif; ?>
                        <?php if ($stream['scheduled_at']): ?><span class="stream-card-meta-item">📅 <?= date('d M Y, H:i', strtotime($stream['scheduled_at'])) ?></span><?php endif; ?>
                    </div>
                </div>
                <a href="<?= htmlspecialchars($stream['url']) ?>" target="_blank" rel="noopener" class="btn <?= $stream['is_live'] ? 'btn-danger' : 'btn-primary' ?> btn-sm">
                    <?= $stream['is_live'] ? '📺 Ver EN VIVO' : '▶️ Ver' ?>
                </a>
            </div>

            <?php if ($stream['is_live'] && $embed['embed_url']): ?>
            <!-- Embedded player -->
            <div class="stream-embed-wrap">
                <iframe src="<?= htmlspecialchars($embed['embed_url']) ?>"
                        class="stream-embed-frame"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                        loading="lazy">
                </iframe>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
    <div class="empty-state">
        <div class="empty-state-icon">📺</div>
        <h3 class="empty-state-title">No hay transmisiones programadas</h3>
        <p>Las transmisiones aparecerán aquí cuando los admins las configuren</p>
    </div>
    <?php endif; ?>

</div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
