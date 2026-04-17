<?php
/**
 * Liga WOC - Front Controller / Router
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

// Initialize error handling
require_once __DIR__ . '/libs/ErrorHandler.php';

// Get the route
$route = isset($_GET['route']) ? trim($_GET['route'], '/') : '';

// If no ?route= param, extract from the URL path (PHP built-in server doesn't process .htaccess)
if (empty($route)) {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $base = rtrim(BASE_URL, '/'); // e.g. /LIGAWOC
    if ($base && strpos($uri, $base) === 0) {
        $path = trim(substr($uri, strlen($base)), '/');
        if ($path && $path !== 'index.php') {
            $route = $path;
        }
    }
}

$method = $_SERVER['REQUEST_METHOD'];

// Split route into segments
$segments = $route ? explode('/', $route) : [];
$page = $segments[0] ?? 'home';
$action = $segments[1] ?? null;
$id = $segments[2] ?? null;

// Load libraries
require_once __DIR__ . '/libs/EmailService.php';
require_once __DIR__ . '/libs/RateLimiter.php';
require_once __DIR__ . '/libs/PasswordValidator.php';
require_once __DIR__ . '/libs/ResourceValidator.php';

// Load controllers
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/NewsController.php';
require_once __DIR__ . '/controllers/TeamController.php';
require_once __DIR__ . '/controllers/TournamentController.php';
require_once __DIR__ . '/controllers/RankingController.php';
require_once __DIR__ . '/controllers/NotificationController.php';
require_once __DIR__ . '/controllers/ShopController.php';

// API Routes
if ($page === 'api') {
    header('Content-Type: application/json');
    $apiController = $segments[1] ?? '';
    $apiAction = $segments[2] ?? '';
    $apiId = $segments[3] ?? null;
    
    switch ($apiController) {
        case 'auth':
            $ctrl = new AuthController();
            if ($apiAction === 'register-availability') $ctrl->checkRegisterAvailability();
            break;
        case 'notifications':
            $ctrl = new NotificationController();
            if ($apiAction === 'count') $ctrl->getUnreadCount();
            elseif ($apiAction === 'mark-read') $ctrl->markAsRead($apiId);
            elseif ($apiAction === 'list') $ctrl->getNotifications();
            break;
        case 'teams':
            $ctrl = new TeamController();
            if ($apiAction === 'approve-request') $ctrl->approveRequest($apiId);
            elseif ($apiAction === 'reject-request') $ctrl->rejectRequest($apiId);
            elseif ($apiAction === 'kick-member') $ctrl->kickMember($apiId);
            elseif ($apiAction === 'change-role') $ctrl->changeMemberRole($apiId);
            break;
        case 'tournaments':
            $ctrl = new TournamentController();
            if ($apiAction === 'bracket') $ctrl->getBracketData($apiId);
            break;
        case 'shop':
            require_once __DIR__ . '/pages/api/shop-item.php';
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'API endpoint not found']);
    }
    exit;
}

// Auth Routes (no login required)
$publicPages = ['home', 'login', 'register', 'verify', 'forgot-password', 'reset-password', '404', '403', '500', 'user'];

// Handle auth actions
if ($page === 'login' && $method === 'POST') {
    $auth = new AuthController();
    $auth->login();
    exit;
}

if ($page === 'register' && $method === 'POST') {
    $auth = new AuthController();
    $auth->register();
    exit;
}

if ($page === 'logout') {
    $auth = new AuthController();
    $auth->logout();
    exit;
}

if ($page === 'verify' && $method === 'POST') {
    $auth = new AuthController();
    if ($action === 'resend') {
        $auth->resendCode();
    } else {
        $auth->verify();
    }
    exit;
}

if ($page === 'forgot-password' && $method === 'POST') {
    $auth = new AuthController();
    $auth->forgotPassword();
    exit;
}

if ($page === 'reset-password' && $method === 'POST') {
    $auth = new AuthController();
    $auth->resetPassword();
    exit;
}

// Protected pages require login
if (!in_array($page, $publicPages) && !isLoggedIn()) {
    setFlash('warning', 'Debes iniciar sesión para acceder a esta página.');
    redirect('login');
}

// Admin routes
if ($page === 'admin') {
    if (!isAdmin() && !isModerator()) {
        redirect('dashboard');
    }
    $adminPage = $action ?? 'index';
    // Moderadores solo pueden acceder a gestion de usuarios
    if (!isAdmin() && isModerator() && $adminPage !== 'users') {
        redirect('admin/users');
    }
    $adminFile = __DIR__ . '/admin/' . $adminPage . '.php';
    
    if (file_exists($adminFile)) {
        require_once $adminFile;
    } else {
        $page = '404';
        require_once __DIR__ . '/pages/404.php';
    }
    exit;
}

// Handle form submissions for protected routes
if ($method === 'POST') {
    $postHandled = true;
    switch ($page) {
            case 'perfilNuevo':
                $pageFile = 'perfilNuevo';
                break;
        case 'teams':
            $ctrl = new TeamController();
            if ($action === 'create') $ctrl->create();
            elseif ($action === 'join') $ctrl->joinRequest($id);
            elseif ($action === 'update') $ctrl->update($id);
            elseif ($action === 'leave') $ctrl->leaveTeam($id);
            elseif ($action === 'transfer-leadership') $ctrl->transferLeadership($id);
            break;
        case 'tournaments':
            $ctrl = new TournamentController();
            if ($action === 'register') $ctrl->registerTeam($id);
            elseif ($action === 'leave') $ctrl->leaveTournament($id);
            elseif ($action === 'submit-result') $ctrl->submitResult($id);
            break;
        case 'profile':
            $auth = new AuthController();
            if ($action === 'send-email-code') {
                $auth->sendEmailVerificationCode();
            } elseif ($action === 'verify-email-code') {
                $auth->verifyEmailCode();
            } elseif ($action === 'resend-email-code') {
                $auth->resendEmailVerificationCode();
            } elseif ($action === 'upload-cover' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                verifyCsrf();
                if (isset($_FILES['cover'])) {
                    require_once __DIR__ . '/libs/FileValidator.php';
                    
                    // Validar imagen con magic bytes
                    $validation = FileValidator::validateImage($_FILES['cover'], 5 * 1024 * 1024);
                    
                    if ($validation['success']) {
                        $fileInfo = $validation['file'];
                        $requiredCoverWidth = 1920;
                        $requiredCoverHeight = 500;
                        $coverDimensions = @getimagesize($fileInfo['tmp_name']);

                        if ($coverDimensions === false) {
                            setFlash('error', 'No se pudieron leer las dimensiones de la portada.');
                            redirect('profile');
                        }

                        $coverWidth = (int)$coverDimensions[0];
                        $coverHeight = (int)$coverDimensions[1];

                        if ($coverWidth !== $requiredCoverWidth || $coverHeight !== $requiredCoverHeight) {
                            setFlash('error', 'La portada debe medir exactamente 1920 x 500 px. Imagen recibida: ' . $coverWidth . ' x ' . $coverHeight . ' px.');
                            redirect('profile');
                        }

                        $uploadDir = __DIR__ . '/assets/covers/';
                        
                        // Crear directorio si no existe
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0755, true);
                        }
                        
                        // Mover archivo a directorio de uploads
                        $moveResult = FileValidator::moveToPrivateStorage(
                            $fileInfo['tmp_name'],
                            $fileInfo['safe_name'],
                            $uploadDir
                        );
                        
                        if ($moveResult['success']) {
                            $db = Database::getInstance();
                            
                            // Eliminar cover anterior
                            $oldCover = $db->fetch("SELECT cover FROM users WHERE id = ?", [currentUserId()]);
                            if ($oldCover && $oldCover['cover'] && file_exists($uploadDir . $oldCover['cover'])) {
                                @unlink($uploadDir . $oldCover['cover']);
                            }
                            
                            // Guardar nombre del archivo seguro en BD
                            $db->update("UPDATE users SET cover = ? WHERE id = ?", [$fileInfo['safe_name'], currentUserId()]);
                            $_SESSION['user_cover'] = $fileInfo['safe_name'];
                            setFlash('success', 'Portada actualizada correctamente.');
                        } else {
                            setFlash('error', $moveResult['error']);
                        }
                    } else {
                        setFlash('error', 'Error en validación de imagen: ' . $validation['error']);
                    }
                }
                redirect('profile');
            } else {
                $auth->updateProfile();
            }
            break;
        case 'complete-profile':
            $auth = new AuthController();
            $auth->completeProfile();
            break;
        case 'news':
            if ($action === 'create' && isAdmin()) {
                $ctrl = new NewsController();
                $ctrl->create();
            }
            break;
        case 'shop':
            require_once __DIR__ . '/controllers/ShopController.php';
            $shop = new ShopController();
            if ($action === 'purchase' && isLoggedIn()) {
                $itemId = intval($_POST['item_id'] ?? 0);
                $result = $shop->purchaseItem(currentUserId(), $itemId);
                if ($result['success']) {
                    setFlash('success', '¡Compra exitosa! Has adquirido: ' . $result['item']['name']);
                } else {
                    setFlash('error', $result['error']);
                }
                redirect('inventory');
            }
            break;
        case 'buy-coins':
            require_once __DIR__ . '/controllers/ShopController.php';
            require_once __DIR__ . '/controllers/PayPalController.php';
            $shop = new ShopController();
            $paypal = new PayPalController();
            
            if ($action === 'verify' && isLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST') {
                header('Content-Type: application/json');

                if (trim((string) PAYPAL_CLIENT_ID) === '' || trim((string) PAYPAL_CLIENT_SECRET) === '') {
                    echo json_encode(['success' => false, 'error' => 'PayPal no está configurado en el servidor.']);
                    exit;
                }

                $orderID = $_POST['orderID'] ?? '';
                $packageId = intval($_POST['package_id'] ?? 0);
                
                if (empty($orderID) || empty($packageId)) {
                    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                    exit;
                }
                
                $result = $paypal->verifyAndCreditOrder($orderID, $packageId, currentUserId());
                echo json_encode($result);
                exit;
            }
            break;
        default:
            // Pages like match-history handle their own POST logic
            $postHandled = false;
            break;
    }
    if ($postHandled) exit;
}

// Page routing
$pageFile = null;
switch ($page) {
    case 'home':
    case '':
        if (isLoggedIn()) {
            if (isAdmin()) redirect('admin');
            redirect('dashboard');
        }
        $pageFile = 'home';
        break;
    case 'dashboard':
        if (isAdmin()) redirect('admin');
        $pageFile = 'dashboard';
        break;
    case 'news':
        if ($action === 'view' && $id) {
            $_GET['slug'] = $id;
            $pageFile = 'news-view';
        } else {
            $pageFile = 'news';
        }
        break;
    case 'teams':
        if ($action === 'create') $pageFile = 'team-create';
        elseif ($action === 'view' && $id) { $_GET['id'] = $id; $pageFile = 'team-view'; }
        elseif ($action === 'join' && $id) { $_GET['id'] = $id; $pageFile = 'team-join'; }
        elseif ($action === 'manage' && $id) { $_GET['id'] = $id; $pageFile = 'team-manage'; }
        else $pageFile = 'teams';
        break;
    case 'tournaments':
        if ($action === 'view' && $id) { $_GET['id'] = $id; $pageFile = 'tournament-view'; }
        else $pageFile = 'tournaments';
        break;
    case 'rankings':
        $pageFile = 'rankings';
        break;
    case 'search':
        $pageFile = 'search';
        break;
    case 'calendar':
        $pageFile = 'calendar';
        break;
    case 'stream':
        $pageFile = 'stream';
        break;
    case 'profile':
        if ($action === 'equip-marco' && isset($_GET['item_id'])) {
            verifyCsrfRequest();
            require_once __DIR__ . '/controllers/ShopController.php';
            $resourceValidator = new ResourceValidator();
            if (!$resourceValidator->UserCanAccessInventory(currentUserId(), currentUserId())) {
                ErrorHandler::forbidden('No tienes permiso para modificar este inventario.');
            }
            $shop = new ShopController();
            $result = $shop->equipMarco(currentUserId(), (int)$_GET['item_id']);
            if (!$result['success']) {
                setFlash('error', $result['error'] ?? 'No se pudo equipar el marco.');
            }
            redirect('profile');
        } elseif ($action === 'equip-portada' && isset($_GET['item_id'])) {
            verifyCsrfRequest();
            require_once __DIR__ . '/controllers/ShopController.php';
            $resourceValidator = new ResourceValidator();
            if (!$resourceValidator->UserCanAccessInventory(currentUserId(), currentUserId())) {
                ErrorHandler::forbidden('No tienes permiso para modificar este inventario.');
            }
            $shop = new ShopController();
            $result = $shop->equipPortada(currentUserId(), (int)$_GET['item_id']);
            if (!$result['success']) {
                setFlash('error', $result['error'] ?? 'No se pudo equipar la portada.');
            }
            redirect('profile');
        }
        $pageFile = 'profile';
        break;
    case 'match-history':
        $pageFile = 'match-history';
        break;
    case 'notifications':
        $pageFile = 'notifications';
        break;
    case 'user':
        if ($action === 'view' && $id) { $_GET['id'] = $id; $pageFile = 'user-profile'; }
        else { $pageFile = '404'; }
        break;
    case 'login':
        if (isLoggedIn()) redirect('dashboard');
        $pageFile = 'login';
        break;
    case 'register':
        if (isLoggedIn()) redirect('dashboard');
        $pageFile = 'register';
        break;
    case 'verify':
        $pageFile = 'verify';
        break;
    case 'forgot-password':
        $pageFile = 'forgot-password';
        break;
    case 'reset-password':
        $pageFile = 'reset-password';
        break;
    case 'complete-profile':
        $pageFile = 'complete-profile';
        break;
    case 'shop':
        $pageFile = 'shop';
        break;
    case 'inventory':
        if ($action === 'equip' && $id) {
            verifyCsrfRequest();
            require_once __DIR__ . '/controllers/ShopController.php';
            $resourceValidator = new ResourceValidator();
            if (!$resourceValidator->UserCanAccessInventory(currentUserId(), currentUserId())) {
                ErrorHandler::forbidden('No tienes permiso para modificar este inventario.');
            }
            $shop = new ShopController();
            $result = $shop->equipItem(currentUserId(), (int)$id);
            if (!$result['success']) {
                setFlash('error', $result['error'] ?? 'No se pudo equipar el item.');
            }
            redirect('inventory');
        } elseif ($action === 'unequip' && $id) {
            verifyCsrfRequest();
            require_once __DIR__ . '/controllers/ShopController.php';
            $resourceValidator = new ResourceValidator();
            if (!$resourceValidator->UserCanAccessInventory(currentUserId(), currentUserId())) {
                ErrorHandler::forbidden('No tienes permiso para modificar este inventario.');
            }
            $shop = new ShopController();
            $result = $shop->unequipItem(currentUserId(), (int)$id);
            if (!$result['success']) {
                setFlash('error', $result['error'] ?? 'No se pudo quitar el item.');
            }
            redirect('inventory');
        } else {
            $pageFile = 'inventory';
        }
        break;
    case 'buy-coins':
        $pageFile = 'buy-coins';
        break;
    case 'designer':
        $pageFile = 'designer';
        break;
    case 'heroes':
        $heroesFile = __DIR__ . '/heroes.php';
        if (file_exists($heroesFile)) {
            require_once $heroesFile;
            exit;
        }
        $pageFile = '404';
        break;
    default:
        $pageFile = '404';
        break;
}

if ($pageFile) {
    $filePath = __DIR__ . '/pages/' . $pageFile . '.php';
    if (file_exists($filePath)) {
        require_once $filePath;
    } else {
        require_once __DIR__ . '/pages/404.php';
    }
}
