<?php
/**
 * Liga WOC - Application Configuration
 */

// Cargar variables de entorno
require_once __DIR__ . '/env-loader.php';

// ====================================
// HTTPS ENFORCEMENT - SEGURIDAD
// ====================================
$isLocal = in_array($_SERVER['HTTP_HOST'] ?? 'localhost', ['localhost', '127.0.0.1']) 
           || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
$isProduction = env('APP_ENV') === 'production';

// Forzar HTTPS en producción
if ($isProduction && !$isLocal) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    
    if ($protocol === 'http') {
        // Redirigir a HTTPS
        $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('Location: ' . $url, true, 301);
        exit;
    }
}

// Error reporting - desactivado en producción
if ($isProduction) {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', dirname(__DIR__) . '/logs/error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Session
if (session_status() === PHP_SESSION_NONE) {
    // Session timeout: 1 hora por defecto
    $sessionTimeout = env('SESSION_TIMEOUT', 3600);
    $secureCookies = env('SECURE_COOKIES', 'true') === 'true';
    $sameSite = env('SAMESITE_COOKIES', 'Strict');
    
    session_set_cookie_params([
        'lifetime' => $sessionTimeout,
        'path' => '/',
        'secure' => $secureCookies && (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'httponly' => true,
        'samesite' => $sameSite  // 'Strict' para máxima seguridad
    ]);
    session_start();
    
    // Regenerate session ID on init to prevent session hijacking
    if (!isset($_SESSION['_initialized'])) {
        session_regenerate_id(true);
        $_SESSION['_initialized'] = time();
    }
    
    // Invalidar sesión después de timeout de inactividad
    if (isset($_SESSION['ACTIVITY']) && (time() - $_SESSION['ACTIVITY'] > $sessionTimeout)) {
        session_destroy();
        header('Location: ' . BASE_URL . 'login', true, 302);
        exit;
    }
    $_SESSION['ACTIVITY'] = time();
}

// Base paths
define('BASE_PATH', dirname(__DIR__) . '/');

// Auto-detect environment: /LIGAWOC/ for localhost, / for production
$_host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]);
$isLocal = in_array($_host, ['localhost', '127.0.0.1', '::1']);
define('BASE_URL', $isLocal ? '/LIGAWOC/' : '/');
define('SITE_NAME', 'Liga WOC');
define('SITE_DESCRIPTION', 'Plataforma de Torneos de Mobile Legends');
define('SITE_URL', $isLocal ? 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/LIGAWOC' : 'https://ligawocdominicana.com');

// Upload paths
define('UPLOAD_PATH', BASE_PATH . 'assets/uploads/');
define('UPLOAD_URL', BASE_URL . 'assets/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// Mail Settings (SMTP) - via variables de entorno
define('SMTP_HOST', env('SMTP_HOST', 'mail.ligawocdominicana.com'));
define('SMTP_USER', env('SMTP_USER', 'info@ligawocdominicana.com'));
define('SMTP_PASS', env('SMTP_PASS', ''));
define('SMTP_PORT', env('SMTP_PORT', 465));
define('SMTP_SECURE', env('SMTP_SECURE', 'ssl')); // ssl (465) o tls (587)
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'Liga WOC'));
define('SMTP_DEBUG', filter_var(env('SMTP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN));

// Pagination
define('ITEMS_PER_PAGE', 12);

// Roles
define('ROLE_PLAYER', 'player');
define('ROLE_MODERATOR', 'moderator');
define('ROLE_ADMIN', 'admin');
define('ROLE_SUPERADMIN', 'superadmin');

// ML Roles
define('ML_ROLES', [
    'adc' => 'ADC (Tirador)',
    'mage' => 'Mago',
    'tank' => 'Tanque',
    'assassin' => 'Asesino',
    'fighter' => 'Combatiente',
    'support' => 'Apoyo',
    'manager' => 'Manager',
    'coach' => 'Coach'
]);

// ML Lanes
define('ML_LANES', [
    'gold' => 'Gold Lane',
    'mid' => 'Mid Lane',
    'exp' => 'EXP Lane',
    'roam' => 'Roam',
    'jungle' => 'Jungla'
]);

// ML Ranks
define('ML_RANKS', [
    'warrior' => 'Guerrero',
    'elite' => 'Élite',
    'master' => 'Maestro',
    'grandmaster' => 'Gran Maestro',
    'epic' => 'Épica',
    'legend' => 'Leyenda',
    'mythic' => 'Mítico',
    'mythical_honor' => 'Honor Mítico',
    'mythical_glory' => 'Gloria Mítica',
    'mythical_immortal' => 'Inmortal Mítico'
]);

// Rank image file map
define('ML_RANK_IMAGES', [
    'warrior' => 'guerrero.png',
    'elite' => 'elite.png',
    'master' => 'Maestro.png',
    'grandmaster' => 'Gran maestro.png',
    'epic' => 'epico.png',
    'legend' => 'Leyenda.png',
    'mythic' => 'Mitico.png',
    'mythical_honor' => 'Mitico honorario.png',
    'mythical_glory' => 'Gloria Mitica.png',
    'mythical_immortal' => 'Mitico inmortal.png'
]);

// Role → hero image folder map
define('ML_ROLE_FOLDERS', [
    'adc' => 'TIRADOR',
    'mage' => 'MAGO',
    'tank' => 'Tanque',
    'assassin' => 'ASESINO',
    'fighter' => 'Combatiente',
    'support' => 'APOYO',
    'manager' => 'APOYO',
    'coach' => 'APOYO'
]);

// Role → image mapping
define('ML_ROLE_IMAGES', [
    'adc' => 'TIRADOR.PNG',
    'mage' => 'MAGO.PNG',
    'tank' => 'tanque.PNG',
    'assassin' => 'ASESINO.png',
    'fighter' => 'COMBATIENTE.png',
    'support' => 'APOYO.png',
    'manager' => 'manager.png',
    'coach' => 'coach.png'
]);

// Lane → image mapping
define('ML_LANE_IMAGES', [
    'gold' => 'Linea Oro.png',
    'mid' => 'linea media.png',
    'exp' => 'Linea Experiencia.png',
    'roam' => 'recorrer.png',
    'jungle' => 'Jungla.png'
]);

// Tournament formats
define('TOURNAMENT_FORMATS', [
    'single_elimination' => 'Eliminación Simple',
    'double_elimination' => 'Eliminación Doble',
    'group_stage' => 'Fase de Grupos + Playoffs',
    'round_robin' => 'Formato Liga (Todos contra Todos)'
]);

// Tournament preset: LIGA WOC
define('LIGA_WOC_PRESET', [
    'name' => 'LIGA WOC',
    'format' => 'group_stage',
    'max_teams' => 32,
    'team_size' => 5,
    'is_main_tournament' => 1,
    'tournament_type' => 'liga_woc',
    'description' => 'Torneo principal de Liga WOC. 32 equipos en 8 grupos de 4. Los dos primeros de cada grupo avanzan a las llaves eliminatorias.',
    'rules' => "1. Fase de Grupos: 8 grupos de 4 equipos, todos contra todos.\n2. Los 2 primeros de cada grupo pasan a llaves.\n3. Llaves: Eliminación directa (Octavos → Cuartos → Semifinal → Final).\n4. Partidas Bo1 en fase de grupos, Bo3 en llaves.",
]);

// Points system
define('POINTS_WIN', 3);
define('POINTS_LOSS', 0);
define('POINTS_DRAW', 1);

/**
 * Helper functions
 */

function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

function asset($path) {
    return BASE_URL . 'assets/' . ltrim($path, '/');
}

function redirect($path) {
    $target = url($path);
    if (!headers_sent()) {
        header('Location: ' . $target);
        exit;
    }

    echo '<script>window.location.href=' . json_encode($target) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], [ROLE_ADMIN, ROLE_SUPERADMIN]);
}

function isModerator() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === ROLE_MODERATOR;
}

function isModOrAdmin() {
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], [ROLE_MODERATOR, ROLE_ADMIN, ROLE_SUPERADMIN]);
}

function isSuperAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === ROLE_SUPERADMIN;
}

function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function currentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function currentUsername() {
    return $_SESSION['username'] ?? null;
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}


function verifyCsrf() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Error de seguridad: Token CSRF inválido.');
    }
}

// Para peticiones AJAX/JSON
function verifyCsrfFromJson() {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['csrf_token'] ?? null;
    if (!$token || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        die(json_encode(['error' => 'Error de seguridad: Token CSRF inválido.']));
    }
}

/**
 * Verificar CSRF token en POST o GET requests
 * Soporta tanto $_POST['csrf_token'] como parámetro URL
 * Recomendado para acciones que vienen de GET pero deben validarse
 */
function verifyCsrfRequest() {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    $valid = !empty($token) && $token === ($_SESSION['csrf_token'] ?? '');
    
    if (!$valid) {
        http_response_code(403);
        die(json_encode(['error' => 'Error de seguridad: Token CSRF inválido.']));
    }
    return true;
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' año' . ($diff->y > 1 ? 's' : '');
    if ($diff->m > 0) return $diff->m . ' mes' . ($diff->m > 1 ? 'es' : '');
    if ($diff->d > 0) return $diff->d . ' día' . ($diff->d > 1 ? 's' : '');
    if ($diff->h > 0) return $diff->h . ' hora' . ($diff->h > 1 ? 's' : '');
    if ($diff->i > 0) return $diff->i . ' min';
    return 'ahora';
}

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return $text;
}

/**
 * Enmascarar email parcialmente para privacidad
 * ejemplo@domain.com -> ex****@domain.com
 */
function maskEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    
    $parts = explode('@', $email);
    $localPart = $parts[0];
    $domain = $parts[1];
    
    // Mostrar solo los primeros 2 caracteres
    $visible = min(2, strlen($localPart));
    $masked = substr($localPart, 0, $visible) . str_repeat('*', max(0, strlen($localPart) - $visible));
    
    return $masked . '@' . $domain;
}

/**
 * Enmascarar número de teléfono
 * +1234567890 -> +123****7890
 */
function maskPhone($phone) {
    $clean = preg_replace('/[^0-9+]/', '', $phone);
    if (strlen($clean) < 4) {
        return '****';
    }
    
    $visible_start = 3;
    $visible_end = 4;
    $start = substr($clean, 0, $visible_start);
    $end = substr($clean, -$visible_end);
    $middle = str_repeat('*', strlen($clean) - $visible_start - $visible_end);
    
    return $start . $middle . $end;
}

/**
 * Enmascarar Discord/Username
 * username#1234 -> us****#1234
 */
function maskDiscord($discord) {
    if (empty($discord)) {
        return 'N/A';
    }
    
    $parts = explode('#', $discord);
    if (count($parts) !== 2) {
        return 'N/A';
    }
    
    $username = $parts[0];
    $discriminator = $parts[1];
    
    $visible = min(2, strlen($username));
    $masked = substr($username, 0, $visible) . str_repeat('*', max(0, strlen($username) - $visible));
    
    return $masked . '#' . $discriminator;
}

/**
 * Obtener permiso para ver email
 * Solo superadmin o el usuario mismo puede ver su email real
 */
function canViewFullEmail($targetUserId = null) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // SuperAdmin puede ver todo
    if (isSuperAdmin()) {
        return true;
    }
    
    // User puede ver su propio email
    if ($targetUserId === currentUserId()) {
        return true;
    }
    
    return false;
}
