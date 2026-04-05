    </div><!-- .main-content -->

</div><!-- .app-wrapper -->
<footer style="text-align:center; margin: 24px 0 8px 0; font-size: 0.98em;">
    <a href="/LIGAWOC/PRIVACIDAD_Y_TERMINOS.html" target="_blank" 
         style="margin:0 10px; text-decoration:underline; color:rgba(255,255,255,0.92); transition:color 0.2s; font-weight:500; letter-spacing:0.01em;"
         onmouseover="this.style.color='#ffd600'" onmouseout="this.style.color='rgba(255,255,255,0.92)'">
        Política de Privacidad y Términos
    </a>
</footer>

<?php
$appJsPath = __DIR__ . '/../assets/js/app.js';
$layoutNavbarJsPath = __DIR__ . '/../assets/js/layout/navbar.js';
$pageJsPath = __DIR__ . '/../assets/js/pages/' . ($pageStyleSlug ?? 'default') . '.js';

$appJsVersion = file_exists($appJsPath) ? filemtime($appJsPath) : time();
$layoutNavbarJsVersion = file_exists($layoutNavbarJsPath) ? filemtime($layoutNavbarJsPath) : null;
$pageJsVersion = file_exists($pageJsPath) ? filemtime($pageJsPath) : null;
?>
<script src="<?= asset('js/app.js?v=' . $appJsVersion) ?>"></script>
<?php if ($layoutNavbarJsVersion !== null): ?>
<script src="<?= asset('js/layout/navbar.js?v=' . $layoutNavbarJsVersion) ?>"></script>
<?php endif; ?>
<?php if ($pageJsVersion !== null): ?>
<script src="<?= asset('js/pages/' . ($pageStyleSlug ?? 'default') . '.js?v=' . $pageJsVersion) ?>"></script>
<?php endif; ?>
</body>
</html>
