<?php $pageTitle = '404 - No Encontrado'; ?>
<?php $pageCss = '404'; ?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-404">
  <div class="bg-layer"></div>
  <div class="bg-grid"></div>
  <div class="bg-slash"></div>
  <div class="scanlines"></div>
  <div class="grain"></div>

  <div class="hud-corner tl"></div>
  <div class="hud-corner tr"></div>
  <div class="hud-corner bl"></div>
  <div class="hud-corner br"></div>

  <div class="corner-code-tl">
    LIGA_WOC :: SYS<br>
    BUILD 2.4.1 STABLE<br>
    SESSION TERMINATED
  </div>
  <div class="corner-code">
    ERR_CODE: 0x1A4<br>
    HTTP STATUS: 404<br>
    <?= date('Y.m.d H:i:s') ?>
  </div>

  <div class="silhouette sil-l">⚔</div>
  <div class="silhouette sil-r">🛡</div>

  <div class="debris" id="debris"></div>

  <div class="content-404">
    <div class="error-badge">Sistema Offline — Ruta no encontrada</div>

    <div class="giant-404">
      <div class="glitch-a">404</div>
      <div class="glitch-b">404</div>
      <span class="num">404</span>
    </div>

    <div class="hud-divider">
      <span>⬛ CONNECTION LOST ⬛</span>
    </div>

    <div class="terminal-box">
      <div class="terminal-line">
        <span class="prompt">$</span>
        <span class="cmd">liga-woc route resolve <span class="warn">--path=/???</span></span>
      </div>
      <div class="terminal-line">
        <span class="prompt">›</span>
        <span class="err">ERROR: Destination node unreachable</span>
      </div>
      <div class="terminal-line">
        <span class="prompt">›</span>
        <span class="warn">WARN: Route map corrupted or deleted</span>
      </div>
      <div class="terminal-line">
        <span class="prompt">›</span>
        <span class="info">INFO: Attempting fallback to base node...</span>
      </div>
      <div class="terminal-line">
        <span class="prompt">$</span>
        <span class="ok">READY</span> <span class="cursor-blink"></span>
      </div>
    </div>

    <p class="subtitle-main">
      Esta página no existe, fue eliminada o nunca estuvo en el mapa de Liga WOC.
    </p>

    <div class="actions-404">
      <a href="<?= function_exists('url') ? url('') : '/' ?>" class="btn-404 btn-primary-404">
        <span>⌂</span> Volver al Inicio
      </a>
      <a href="#" class="btn-404 btn-ghost-404" data-history-back>
        ← Página Anterior
      </a>
    </div>

    <div class="status-row">
      <div class="status-item"><div class="status-dot red"></div> Ruta</div>
      <div class="status-item"><div class="status-dot green"></div> Servidor</div>
      <div class="status-item"><div class="status-dot green"></div> Base de Datos</div>
      <div class="status-item"><div class="status-dot amber"></div> CDN</div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
