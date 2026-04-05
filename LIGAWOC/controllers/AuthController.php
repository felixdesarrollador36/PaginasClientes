<?php
/**
 * Liga WOC - Auth Controller
 */

class AuthController {
    private $db;
    private $rateLimiter;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->rateLimiter = new RateLimiter();
    }

    public function register() {
        verifyCsrf();
        
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        // Validation
        $errors = [];
        if (strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = 'El nombre de usuario debe tener entre 3 y 50 caracteres.';
        }
        if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
            $errors[] = 'El usuario solo puede contener letras y números, sin espacios ni guiones.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido.';
        }
        
        // Registro: permitir cualquier contraseña de hasta 9 caracteres
        if ($password !== $confirm) {
            $errors[] = 'Las contraseñas no coinciden.';
        }
        if (strlen($password) !== 9) {
            $errors[] = 'La contraseña debe tener exactamente 9 caracteres.';
        }

        // Check username/email uniqueness
        $existing = $this->db->fetch("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($existing) {
            $errors[] = 'El nombre de usuario o email ya está en uso.';
        }

        // Check ML ID uniqueness if provided
        $ml_id = sanitize($_POST['ml_id'] ?? '');
        $ml_server = sanitize($_POST['ml_server'] ?? '');
        $ml_nickname = sanitize($_POST['ml_nickname'] ?? '');
        
        $whatsapp = sanitize($_POST['whatsapp'] ?? '');
        $phone_brand = sanitize($_POST['phone_brand'] ?? '');
        $discord = sanitize($_POST['discord'] ?? '');

        if (empty($ml_id) || empty($ml_server) || empty($ml_nickname)) {
            $errors[] = 'El ID de ML, el Servidor y el Nickname son obligatorios.';
        } else {
            if (!preg_match('/^\d+$/', $ml_id)) {
                $errors[] = 'El ID de ML solo puede contener números.';
            }
            if (!preg_match('/^\d+$/', $ml_server)) {
                $errors[] = 'El servidor de ML solo puede contener números.';
            }

            $existingMl = $this->db->fetch("SELECT id FROM users WHERE ml_id = ?", [$ml_id]);
            if ($existingMl) {
                $errors[] = 'Este ID de Mobile Legends ya está registrado en otra cuenta.';
            }
        }
        
        if (empty($whatsapp) || empty($phone_brand) || empty($discord)) {
            $errors[] = 'Los datos de contacto (WhatsApp, Marca y Discord) son obligatorios.';
        }

        if (!empty($errors)) {
            setFlash('error', implode('<br>', $errors));
            redirect('register');
        }
        
        // Create user
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // Generate 6-digit OTP code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codeExpiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $main_hero = sanitize($_POST['main_hero'] ?? '');
        $current_rank = sanitize($_POST['current_rank'] ?? '');
        
        $this->db->insert(
            "INSERT INTO users (username, email, password, verification_token, is_verified, ml_id, ml_server, ml_nickname, main_hero, current_rank, whatsapp, phone_brand, discord) VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$username, $email, $hash, $code, $ml_id ?: null, $ml_server ?: null, $ml_nickname ?: null, $main_hero ?: null, $current_rank ?: null, $whatsapp, $phone_brand, $discord]
        );

        $mailSent = EmailService::sendVerificationEmail($email, $username, $code);

        // Store email in session to pre-fill the verify form
        $_SESSION['pending_verify_email'] = $email;

        if ($mailSent) {
            setFlash('success', '¡Cuenta creada! Hemos enviado un código de 6 dígitos a tu correo. Úbrelo y ponlo aquí.');
        } else {
            setFlash('warning', 'Tu cuenta fue creada, pero no pudimos enviar el código por correo. Intenta "Reenviar código" en esta pantalla en unos segundos.');
        }
        redirect('verify');
    }

    public function login() {
        verifyCsrf();
        
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $login = sanitize($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (empty($login) || empty($password)) {
            setFlash('error', 'Completa todos los campos.');
            redirect('login');
        }

        $user = $this->db->fetch(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_banned = 0",
            [$login, $login]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            setFlash('error', 'Credenciales incorrectas.');
            redirect('login');
        }

        if (!$user['is_verified']) {
            setFlash('warning', 'Tu cuenta aún no está activada. Por favor revisa tu bandeja de entrada o la carpeta de SPAM para verificar tu correo.');
            redirect('login');
        }

        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_avatar'] = $user['avatar'];
        $_SESSION['user_email'] = $user['email'];

        // Update last login
        $this->db->update("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);

        // Log login exitoso
        error_log("[LOGIN SUCCESS] User: {$user['username']} (ID: {$user['id']}) from IP: {$clientIp}");

        // Redirect based on role
        if (in_array($user['role'], [ROLE_ADMIN, ROLE_SUPERADMIN])) {
            redirect('admin');
        } else {
            redirect('dashboard');
        }
    }

    public function logout() {
        session_destroy();
        redirect('login');
    }

    public function checkRegisterAvailability() {
        $username = sanitize($_GET['username'] ?? '');
        $email = sanitize($_GET['email'] ?? '');
        $mlId = sanitize($_GET['ml_id'] ?? '');

        $response = [
            'username' => ['checked' => false, 'exists' => false],
            'email' => ['checked' => false, 'exists' => false],
            'ml_id' => ['checked' => false, 'exists' => false],
        ];

        if ($username !== '') {
            $exists = $this->db->fetch("SELECT id FROM users WHERE username = ? LIMIT 1", [$username]);
            $response['username'] = ['checked' => true, 'exists' => (bool)$exists];
        }

        if ($email !== '') {
            $exists = $this->db->fetch("SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);
            $response['email'] = ['checked' => true, 'exists' => (bool)$exists];
        }

        if ($mlId !== '') {
            $exists = $this->db->fetch("SELECT id FROM users WHERE ml_id = ? LIMIT 1", [$mlId]);
            $response['ml_id'] = ['checked' => true, 'exists' => (bool)$exists];
        }

        echo json_encode(['success' => true, 'data' => $response]);
        exit;
    }

    public function verify() {
        verifyCsrf();
        
        $email = sanitize($_POST['email'] ?? ($_SESSION['pending_verify_email'] ?? ''));
        $code  = trim($_POST['code'] ?? '');
        
        if (empty($email) || empty($code)) {
            setFlash('error', 'Por favor ingresa tu correo y el código.');
            redirect('verify');
        }
        
        $user = $this->db->fetch(
            "SELECT id FROM users WHERE email = ? AND verification_token = ? AND is_verified = 0",
            [$email, $code]
        );
        
        if ($user) {
            $this->db->update(
                "UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?",
                [$user['id']]
            );
            $userInfo = $this->db->fetch("SELECT username FROM users WHERE id = ?", [$user['id']]);
            EmailService::sendWelcomeEmail($email, $userInfo['username'] ?? 'Jugador');
            unset($_SESSION['pending_verify_email']);
            setFlash('success', '\u00a1Cuenta verificada correctamente! Ya puedes iniciar sesi\u00f3n.');
        } else {
            setFlash('error', 'C\u00f3digo incorrecto o ya utilizado. Verifica e intenta de nuevo.');
            redirect('verify');
        }
        redirect('login');
    }
    
    public function resendCode() {
        verifyCsrf();
        $email = sanitize($_POST['email'] ?? '');
        
        if (empty($email)) {
            setFlash('error', 'Ingresa tu correo para reenviar el código.');
            redirect('verify');
        }
        
        $user = $this->db->fetch("SELECT id, username FROM users WHERE email = ? AND is_verified = 0", [$email]);
        if (!$user) {
            setFlash('error', 'No se encontró una cuenta pendiente con ese correo.');
            redirect('verify');
        }
        
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->db->update("UPDATE users SET verification_token = ? WHERE id = ?", [$code, $user['id']]);
        EmailService::sendVerificationEmail($email, $user['username'], $code);
        
        $_SESSION['pending_verify_email'] = $email;
        setFlash('success', 'Se ha reenviado un nuevo c\u00f3digo a tu correo.');
        redirect('verify');
    }

    // ─── Forgot / Reset Password ──────────────────────────────────────────────
    public function forgotPassword() {
        verifyCsrf();
        $email = sanitize($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Ingresa un correo v\u00e1lido.');
            redirect('forgot-password');
        }

        $user = $this->db->fetch("SELECT id, username FROM users WHERE email = ? AND is_banned = 0", [$email]);
        
        // Always show success to prevent user enumeration
        if ($user) {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $this->db->update("UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?", [$code, $user['id']]);
            EmailService::sendPasswordResetEmail($email, $user['username'], $code);
            $_SESSION['pending_reset_email'] = $email;
        }

        setFlash('success', 'Si ese correo existe en nuestro sistema, recibir\u00e1s un c\u00f3digo en breve.');
        redirect('reset-password');
    }

    public function resetPassword() {
        verifyCsrf();
        $email = sanitize($_POST['email'] ?? ($_SESSION['pending_reset_email'] ?? ''));
        $code  = trim($_POST['code'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $pass2 = $_POST['password_confirm'] ?? '';

        if (empty($email) || empty($code) || strlen($pass) < 6 || $pass !== $pass2) {
            setFlash('error', 'Verifica que todos los campos est\u00e9n correctos y las contrase\u00f1as coincidan (m\u00edn. 6 caracteres).');
            redirect('reset-password');
        }

        $user = $this->db->fetch(
            "SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_expires > NOW()",
            [$email, $code]
        );

        if (!$user) {
            setFlash('error', 'C\u00f3digo incorrecto o expirado. Solicita uno nuevo.');
            redirect('forgot-password');
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $this->db->update(
            "UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?",
            [$hash, $user['id']]
        );

        unset($_SESSION['pending_reset_email']);
        setFlash('success', '\u00a1Contrase\u00f1a actualizada! Ya puedes iniciar sesi\u00f3n.');
        redirect('login');
    }

    // ─── Complete Profile ──────────────────────────────────────────────
    public function completeProfile() {
        verifyCsrf();
        
        $whatsapp = sanitize($_POST['whatsapp'] ?? '');
        $phone_brand = sanitize($_POST['phone_brand'] ?? '');
        $discord = sanitize($_POST['discord'] ?? '');

        if (empty($whatsapp) || empty($phone_brand) || empty($discord)) {
            setFlash('error', 'Todos los campos son obligatorios para continuar.');
            redirect('complete-profile');
        }

        $this->db->update(
            "UPDATE users SET whatsapp = ?, phone_brand = ?, discord = ? WHERE id = ?",
            [$whatsapp, $phone_brand, $discord, currentUserId()]
        );

        setFlash('success', '¡Perfil actualizado! Gracias por completar tu información.');
        redirect('dashboard');
    }

    public function updateProfile() {
        if (!isLoggedIn()) redirect('login');
        verifyCsrf();

        $userId = currentUserId();
        $currentUser = $this->db->fetch("SELECT username, email FROM users WHERE id = ?", [$userId]);
        $submittedUsername = sanitize($_POST['username'] ?? '');
        $username = $submittedUsername !== '' ? $submittedUsername : ($currentUser['username'] ?? '');
        $bio = sanitize($_POST['bio'] ?? '');
        $ml_id = sanitize($_POST['ml_id'] ?? '');
        $ml_server = sanitize($_POST['ml_server'] ?? '');
        $ml_nickname = sanitize($_POST['ml_nickname'] ?? '');

        if ($ml_id !== '' && !preg_match('/^\d+$/', $ml_id)) {
            setFlash('error', 'El ID de ML solo puede contener números.');
            redirect('profile');
        }

        if ($ml_server !== '' && !preg_match('/^\d+$/', $ml_server)) {
            setFlash('error', 'El servidor de ML solo puede contener números.');
            redirect('profile');
        }

        if ($username === '') {
            setFlash('error', 'No se pudo validar tu nombre de usuario.');
            redirect('profile');
        }

        // Check username uniqueness (excluding current user) only if changed
        if ($username !== ($currentUser['username'] ?? '')) {
            $existing = $this->db->fetch(
                "SELECT id FROM users WHERE username = ? AND id != ?",
                [$username, $userId]
            );
            if ($existing) {
                setFlash('error', 'El nombre de usuario ya está en uso.');
                redirect('profile');
            }
        }

        // Handle avatar upload
        $avatarPath = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $avatarPath = $this->uploadAvatar($_FILES['avatar']);
            if (!$avatarPath) {
                setFlash('error', 'Error al subir el avatar. Verifica el formato y tamaño.');
                redirect('profile');
            }
        }

        // Handle password change
        $newPassword = $_POST['new_password'] ?? '';
        $currentPassword = $_POST['current_password'] ?? '';
        
        if (!empty($newPassword)) {
            $user = $this->db->fetch("SELECT password FROM users WHERE id = ?", [$userId]);
            if (!password_verify($currentPassword, $user['password'])) {
                setFlash('error', 'La contraseña actual es incorrecta.');
                redirect('profile');
            }
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->db->update("UPDATE users SET password = ? WHERE id = ?", [$hash, $userId]);
        }

        // Update profile (except email - handled separately)
        $sql = "UPDATE users SET username = ?, bio = ?";
        $params = [$username, $bio];

        // Only update ML fields if they were actually submitted (not disabled in form)
        if (isset($_POST['ml_id'])) {
            $sql .= ", ml_id = ?, ml_server = ?, ml_nickname = ?";
            $params[] = $ml_id;
            $params[] = $ml_server;
            $params[] = $ml_nickname;
        }
        
        if ($avatarPath) {
            $sql .= ", avatar = ?";
            $params[] = $avatarPath;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $userId;
        
        $this->db->update($sql, $params);

        // Update session
        $_SESSION['username'] = $username;
        $_SESSION['user_email'] = $currentUser['email'] ?? ($_SESSION['user_email'] ?? '');
        if ($avatarPath) $_SESSION['user_avatar'] = $avatarPath;

        setFlash('success', 'Perfil actualizado correctamente.');
        redirect('profile');
    }

    public function sendEmailVerificationCode() {
        if (!isLoggedIn()) redirect('login');
        verifyCsrf();

        $userId = currentUserId();
        $newEmail = sanitize($_POST['new_email'] ?? '');

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'El correo electrónico no es válido.');
            redirect('profile');
        }

        $existing = $this->db->fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$newEmail, $userId]);
        if ($existing) {
            setFlash('error', 'Este correo ya está en uso por otro usuario.');
            redirect('profile');
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        $this->db->update(
            "INSERT INTO email_change_codes (user_id, new_email, code, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))",
            [$userId, $newEmail, $code]
        );

        $user = $this->db->fetch("SELECT username FROM users WHERE id = ?", [$userId]);
        $mailSent = EmailService::sendEmailChangeCode($newEmail, $user['username'], $code);

        $_SESSION['email_change_pending'] = $newEmail;
        if ($mailSent) {
            setFlash('success', 'Se reenvió el código de verificación al nuevo correo.');
        } else {
            setFlash('error', 'No se pudo reenviar el código al nuevo correo. Intenta nuevamente.');
        }
        redirect('profile');
    }

    public function verifyEmailCode() {
        if (!isLoggedIn()) redirect('login');
        verifyCsrf();

        $userId = currentUserId();
        $newEmail = sanitize($_POST['new_email'] ?? '');
        $code = sanitize($_POST['code'] ?? '');

        $record = $this->db->fetch(
            "SELECT * FROM email_change_codes WHERE user_id = ? AND new_email = ? AND code = ? AND expires_at > NOW() AND used = 0 ORDER BY created_at DESC LIMIT 1",
            [$userId, $newEmail, $code]
        );

        if (!$record) {
            setFlash('error', 'Código inválido o expirado.');
            redirect('profile');
        }

        $this->db->update("UPDATE email_change_codes SET used = 1 WHERE id = ?", [$record['id']]);
        $this->db->update("UPDATE users SET email = ? WHERE id = ?", [$newEmail, $userId]);

        $_SESSION['user_email'] = $newEmail;
        $_SESSION['email_change_success'] = true;
        redirect('profile');
    }

    public function resendEmailVerificationCode() {
        if (!isLoggedIn()) redirect('login');
        verifyCsrf();

        $userId = currentUserId();
        
        $record = $this->db->fetch(
            "SELECT new_email FROM email_change_codes WHERE user_id = ? AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1",
            [$userId]
        );

        if (!$record) {
            setFlash('error', 'No hay solicitud de cambio de email activa.');
            redirect('profile');
        }

        $newEmail = $record['new_email'];
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        $this->db->update(
            "UPDATE email_change_codes SET code = ?, expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE user_id = ? AND used = 0",
            [$code, $userId]
        );

        $user = $this->db->fetch("SELECT username FROM users WHERE id = ?", [$userId]);
        EmailService::sendEmailChangeCode($newEmail, $user['username'], $code);

        $_SESSION['email_change_pending'] = $newEmail;
        redirect('profile');
    }

    private function uploadAvatar($file) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $allowed)) {
            setFlash('error', 'Solo se permiten imágenes JPG, PNG, WEBP o GIF.');
            return false;
        }
        if ($file['size'] > 2 * 1024 * 1024) { // 2MB limit
            setFlash('error', 'La imagen no puede pesar más de 2MB.');
            return false;
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . currentUserId() . '_' . time() . '.' . $ext;
        $uploadDir = UPLOAD_PATH . 'avatars/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $dest = $uploadDir . $filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return 'avatars/' . $filename;
        }
        return false;
    }

    public function getUser($id) {
        return $this->db->fetch("SELECT id, username, email, role, avatar, cover, ml_id, ml_server, ml_nickname, bio, phone_brand, created_at, last_login FROM users WHERE id = ?", [$id]);
    }

    public function getAllUsers($page = 1, $search = '') {
        $offset = ($page - 1) * ITEMS_PER_PAGE;
        $params = [];
        $where = "WHERE 1=1";
        
        if ($search) {
            $where .= " AND (username LIKE ? OR email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $total = $this->db->fetch("SELECT COUNT(*) as total FROM users $where", $params)['total'];
        $params[] = ITEMS_PER_PAGE;
        $params[] = $offset;
        $users = $this->db->fetchAll("SELECT id, username, email, role, avatar, is_verified, is_banned, created_at, last_login, whatsapp, phone_brand, discord, ml_nickname, ml_id, ml_server FROM users $where ORDER BY created_at DESC LIMIT ? OFFSET ?", $params);
        
        return ['users' => $users, 'total' => $total, 'pages' => ceil($total / ITEMS_PER_PAGE)];
    }
}
