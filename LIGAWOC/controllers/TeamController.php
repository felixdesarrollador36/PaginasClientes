    
<?php
/**
 * Liga WOC - Team Controller
 */

class TeamController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        require_once __DIR__ . '/NotificationController.php';
    }

        // Permitir que un usuario salga de su equipo
    public function leaveTeam($teamId) {
        if (!isLoggedIn()) redirect('login');
        $userId = currentUserId();
        // No permitir que el capitán abandone (debe transferir o eliminar el equipo)
        $team = $this->getTeam($teamId);
        if ($team && $team['captain_id'] == $userId) {
            setFlash('error', 'El capitán no puede abandonar el equipo.');
            redirect('teams/view/' . $teamId);
        }
        // Eliminar al usuario de team_members
        $this->db->delete("DELETE FROM team_members WHERE team_id = ? AND user_id = ?", [$teamId, $userId]);
        setFlash('success', 'Has salido del equipo.');
        redirect('teams');
    }
    
    // Transferir liderazgo del equipo a otro miembro
    public function transferLeadership($teamId) {
        if (!isLoggedIn()) {
            echo json_encode(['error' => 'No autorizado']);
            return;
        }
        // Validar CSRF para peticiones JSON
        verifyCsrfFromJson();
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $input['user_id'] ?? null;
        if (!$userId) {
            echo json_encode(['error' => 'Solicitud inválida']);
            return;
        }
        $team = $this->getTeam($teamId);
        if (!$team || $team['captain_id'] != currentUserId()) {
            echo json_encode(['error' => 'No tienes permisos para transferir el liderazgo']);
            return;
        }
        // Verificar que el usuario es miembro del equipo y no es el capitán actual
        $member = $this->db->fetch("SELECT * FROM team_members WHERE team_id = ? AND user_id = ?", [$teamId, $userId]);
        if (!$member || $member['is_captain']) {
            echo json_encode(['error' => 'Miembro inválido para transferir liderazgo']);
            return;
        }
        // Actualizar capitán en teams
        $this->db->update("UPDATE teams SET captain_id = ? WHERE id = ?", [$userId, $teamId]);
        // Actualizar flags en team_members
        $this->db->update("UPDATE team_members SET is_captain = 0 WHERE team_id = ?", [$teamId]);
        $this->db->update("UPDATE team_members SET is_captain = 1 WHERE team_id = ? AND user_id = ?", [$teamId, $userId]);
        // Notificar al nuevo capitán
        $notifCtrl = new NotificationController();
        $notifCtrl->create(
            $userId,
            'team',
            '¡Ahora eres el líder!',
            'Te han transferido el liderazgo del equipo ' . $team['name'],
            'teams/manage/' . $teamId
        );
        echo json_encode(['success' => true]);
    }

    public function create() {
        if (!isLoggedIn()) redirect('login');
        if (isAdmin() || isSuperAdmin()) {
            setFlash('error', 'Los administradores no pueden crear equipos.');
            redirect('teams');
        }
        verifyCsrf();

        // Check if user already has a team
        $existing = $this->db->fetch("SELECT id FROM team_members WHERE user_id = ?", [currentUserId()]);
        if ($existing) {
            setFlash('error', 'Ya perteneces a un equipo. No puedes crear otro.');
            redirect('teams');
        }

        $name = sanitize($_POST['name'] ?? '');
        $tag = sanitize($_POST['tag'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $region = sanitize($_POST['region'] ?? '');

        if (empty($name) || strlen($name) < 3) {
            setFlash('error', 'El nombre del equipo debe tener al menos 3 caracteres.');
            redirect('teams/create');
        }

        // Check unique name
        $existingTeam = $this->db->fetch("SELECT id FROM teams WHERE name = ?", [$name]);
        if ($existingTeam) {
            setFlash('error', 'Ya existe un equipo con ese nombre.');
            redirect('teams/create');
        }

        // Captain competitive profile data (validated before creating team to avoid partial records)
        $ml_id = sanitize($_POST['ml_id'] ?? '');
        $ml_server = sanitize($_POST['ml_server'] ?? '');
        $ml_nickname = sanitize($_POST['ml_nickname'] ?? '');
        $nickname = sanitize($_POST['nickname'] ?? '');
        $role = $this->normalizeRole($_POST['role'] ?? 'tank');
        $lane_1 = $this->normalizeLane($_POST['lane_1'] ?? null, false);
        $lane_2 = $this->normalizeLane($_POST['lane_2'] ?? null, true);
        $main_hero = !empty($_POST['main_hero']) ? sanitize($_POST['main_hero']) : null;
        $current_rank = !empty($_POST['current_rank']) ? $_POST['current_rank'] : null;

        if ($lane_1 === null) {
            setFlash('error', 'Debes seleccionar una línea principal para crear el equipo.');
            redirect('teams/create');
        }

        if (empty($main_hero)) {
            setFlash('error', 'Debes seleccionar un héroe principal para crear el equipo.');
            redirect('teams/create');
        }

        if ($lane_2 === $lane_1) {
            $lane_2 = null;
        }

        if ($ml_id === '' || $ml_server === '' || $ml_nickname === '') {
            setFlash('error', 'Completa ID de Mobile Legends, Server y Nick en el Juego.');
            redirect('teams/create');
        }

        if (!preg_match('/^\d+$/', $ml_id)) {
            setFlash('error', 'El ID de Mobile Legends solo puede contener números.');
            redirect('teams/create');
        }

        if (!preg_match('/^\d+$/', $ml_server)) {
            setFlash('error', 'El Server solo puede contener números.');
            redirect('teams/create');
        }

        $userMlProfile = $this->db->fetch("SELECT ml_id, ml_server, ml_nickname FROM users WHERE id = ?", [currentUserId()]);
        $this->syncMissingUserMlProfile(currentUserId(), $userMlProfile, $ml_id, $ml_server, $ml_nickname);

        // Handle logo upload
        $logoPath = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logoPath = $this->uploadLogo($_FILES['logo']);
        }

        // Create team
        $teamId = $this->db->insert(
            "INSERT INTO teams (name, tag, logo, description, region, captain_id, max_members) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$name, $tag, $logoPath, $description, $region, currentUserId(), 20]
        );

        // Add captain as member
        $this->db->insert(
            "INSERT INTO team_members (team_id, user_id, ml_id, ml_server, ml_nickname, nickname, role, lane_1, lane_2, main_hero, current_rank, is_captain) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
            [$teamId, currentUserId(), $ml_id, $ml_server, $ml_nickname, $nickname, $role, $lane_1, $lane_2, $main_hero, $current_rank]
        );

        // Create team ranking entry
        $season = $this->db->fetch("SELECT id FROM seasons WHERE is_active = 1 LIMIT 1");
        if ($season) {
            $this->db->insert(
                "INSERT INTO team_rankings (team_id, season_id) VALUES (?, ?)",
                [$teamId, $season['id']]
            );
        }

        setFlash('success', '¡Equipo creado exitosamente! Ahora eres el capitán.');
        redirect('teams/view/' . $teamId);
    }

    public function joinRequest($teamId) {
        if (!isLoggedIn()) redirect('login');
        if (isAdmin() || isSuperAdmin()) {
            setFlash('error', 'Los administradores no pueden unirse a equipos.');
            redirect('teams');
        }
        verifyCsrf();

        // Check if user already in a team
        $existing = $this->db->fetch("SELECT id FROM team_members WHERE user_id = ?", [currentUserId()]);
        if ($existing) {
            setFlash('error', 'Ya perteneces a un equipo. No puedes unirte a otro.');
            redirect('teams');
        }

        // Check pending request
        $pending = $this->db->fetch(
            "SELECT id FROM team_join_requests WHERE user_id = ? AND team_id = ? AND status = 'pending'",
            [currentUserId(), $teamId]
        );
        if ($pending) {
            setFlash('warning', 'Ya tienes una solicitud pendiente para este equipo.');
            redirect('teams/view/' . $teamId);
        }

        // Check team exists and has room
        $team = $this->db->fetch("SELECT * FROM teams WHERE id = ? AND is_active = 1", [$teamId]);
        if (!$team) {
            setFlash('error', 'Equipo no encontrado.');
            redirect('teams');
        }

        $memberCount = $this->db->fetch("SELECT COUNT(*) as cnt FROM team_members WHERE team_id = ?", [$teamId])['cnt'];
        $teamMax = $this->effectiveTeamMaxMembers($team['max_members'] ?? null);
        if ($memberCount >= $teamMax) {
            setFlash('error', 'El equipo ya está completo.');
            redirect('teams/view/' . $teamId);
        }

        $role = $this->normalizeRole($_POST['role'] ?? 'tank');
        $lane_1 = $this->normalizeLane($_POST['lane_1'] ?? null, false);
        $lane_2 = $this->normalizeLane($_POST['lane_2'] ?? null, true);
        $main_hero = !empty($_POST['main_hero']) ? sanitize($_POST['main_hero']) : null;
        $ml_id = sanitize($_POST['ml_id'] ?? '');
        $ml_server = sanitize($_POST['ml_server'] ?? '');
        $ml_nickname = sanitize($_POST['ml_nickname'] ?? '');

        if ($lane_1 === null) {
            setFlash('error', 'Debes seleccionar una línea principal para enviar la solicitud.');
            redirect('teams/view/' . $teamId);
        }

        if (empty($main_hero)) {
            setFlash('error', 'Debes seleccionar un héroe principal para enviar la solicitud.');
            redirect('teams/view/' . $teamId);
        }

        if ($lane_2 === $lane_1) {
            $lane_2 = null;
        }

        if ($ml_id === '' || $ml_server === '' || $ml_nickname === '') {
            setFlash('error', 'Completa ID de Mobile Legends, Server y Nick en el Juego.');
            redirect('teams/view/' . $teamId);
        }

        if (!preg_match('/^\d+$/', $ml_id)) {
            setFlash('error', 'El ID de Mobile Legends solo puede contener números.');
            redirect('teams/view/' . $teamId);
        }

        if (!preg_match('/^\d+$/', $ml_server)) {
            setFlash('error', 'El Server solo puede contener números.');
            redirect('teams/view/' . $teamId);
        }

        $userMlProfile = $this->db->fetch("SELECT ml_id, ml_server, ml_nickname FROM users WHERE id = ?", [currentUserId()]);
        $this->syncMissingUserMlProfile(currentUserId(), $userMlProfile, $ml_id, $ml_server, $ml_nickname);

        // Create request
        $this->db->insert(
            "INSERT INTO team_join_requests (team_id, user_id, ml_id, ml_server, ml_nickname, nickname, role, lane_1, lane_2, main_hero, current_rank, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $teamId,
                currentUserId(),
                $ml_id,
                $ml_server,
                $ml_nickname,
                sanitize($_POST['nickname'] ?? ''),
                $role,
                $lane_1,
                $lane_2,
                $main_hero,
                !empty($_POST['current_rank']) ? $_POST['current_rank'] : null,
                sanitize($_POST['message'] ?? '')
            ]
        );

        // Notify captain
        $notifCtrl = new NotificationController();
        $notifCtrl->create(
            $team['captain_id'],
            'team',
            'Nueva solicitud de unión',
            currentUsername() . ' quiere unirse a ' . $team['name'],
            'teams/manage/' . $teamId
        );

        setFlash('success', '¡Solicitud enviada! El capitán del equipo revisará tu solicitud.');
        redirect('teams/view/' . $teamId);
    }

    public function approveRequest($requestId) {
        if (!isLoggedIn()) {
            echo json_encode(['error' => 'No autorizado']);
            return;
        }
        verifyCsrfRequest();

        $request = $this->db->fetch(
            "SELECT tjr.*, t.captain_id, t.max_members, t.name as team_name, u.email as user_email, u.username as user_username, c.email as cap_email, c.username as cap_username
             FROM team_join_requests tjr 
             JOIN teams t ON tjr.team_id = t.id 
             JOIN users u ON tjr.user_id = u.id
             JOIN users c ON t.captain_id = c.id
             WHERE tjr.id = ? AND tjr.status = 'pending'",
            [$requestId]
        );

        if (!$request || $request['captain_id'] != currentUserId()) {
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        // Check room
        $memberCount = $this->db->fetch("SELECT COUNT(*) as cnt FROM team_members WHERE team_id = ?", [$request['team_id']])['cnt'];
        $teamMax = $this->effectiveTeamMaxMembers($request['max_members'] ?? null);
        if ($memberCount >= $teamMax) {
            echo json_encode(['error' => 'El equipo ya está completo']);
            return;
        }

        // Check user isn't already in a team
        $existingMember = $this->db->fetch("SELECT id FROM team_members WHERE user_id = ?", [$request['user_id']]);
        if ($existingMember) {
            $this->db->update("UPDATE team_join_requests SET status = 'rejected', reviewed_at = NOW() WHERE id = ?", [$requestId]);
            echo json_encode(['error' => 'El jugador ya pertenece a otro equipo']);
            return;
        }

        // Normalize legacy/invalid values to avoid hard DB failures on approval
        $role = $this->normalizeRole($request['role'] ?? 'tank');
        $lane_1 = $this->normalizeLane($request['lane_1'] ?? null, false);
        if ($lane_1 === null) {
            $lane_1 = $this->defaultLaneForRole($role);
        }
        $lane_2 = $this->normalizeLane($request['lane_2'] ?? null, true);
        if ($lane_2 === $lane_1) {
            $lane_2 = null;
        }

        // Add member
        $this->db->insert(
            "INSERT INTO team_members (team_id, user_id, ml_id, ml_server, ml_nickname, nickname, role, lane_1, lane_2, main_hero, current_rank) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$request['team_id'], $request['user_id'], $request['ml_id'], $request['ml_server'], $request['ml_nickname'], $request['nickname'], $role, $lane_1, $lane_2, $request['main_hero'], $request['current_rank']]
        );

        // Update request
        $this->db->update("UPDATE team_join_requests SET status = 'approved', reviewed_at = NOW() WHERE id = ?", [$requestId]);

        // Reject other pending requests from this user
        $this->db->update("UPDATE team_join_requests SET status = 'rejected', reviewed_at = NOW() WHERE user_id = ? AND status = 'pending' AND id != ?", [$request['user_id'], $requestId]);

        // Notify user
        $notifCtrl = new NotificationController();
        $notifCtrl->create(
            $request['user_id'],
            'team',
            '¡Solicitud aprobada!',
            'Has sido aceptado en el equipo ' . $request['team_name'],
            'teams/view/' . $request['team_id']
        );

        // Send Emails
        EmailService::sendTeamJoinApproved($request['user_email'], $request['user_username'], $request['team_name'], $request['team_id']);
        EmailService::sendNewMemberAlert($request['cap_email'], $request['cap_username'], $request['user_username'], $request['team_name'], $request['team_id']);

        echo json_encode(['success' => true, 'message' => 'Jugador aceptado en el equipo']);
    }

    public function rejectRequest($requestId) {
        if (!isLoggedIn()) {
            echo json_encode(['error' => 'No autorizado']);
            return;
        }
        verifyCsrfRequest();

        $request = $this->db->fetch(
            "SELECT tjr.*, t.captain_id, t.name as team_name, u.email as user_email, u.username as user_username
             FROM team_join_requests tjr 
             JOIN teams t ON tjr.team_id = t.id 
             JOIN users u ON tjr.user_id = u.id
             WHERE tjr.id = ? AND tjr.status = 'pending'",
            [$requestId]
        );

        if (!$request || $request['captain_id'] != currentUserId()) {
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $this->db->update("UPDATE team_join_requests SET status = 'rejected', reviewed_at = NOW() WHERE id = ?", [$requestId]);

        $notifCtrl = new NotificationController();
        $notifCtrl->create(
            $request['user_id'],
            'team',
            'Solicitud rechazada',
            'Tu solicitud para unirte a ' . $request['team_name'] . ' fue rechazada.',
            'teams'
        );

        // Send Email
        EmailService::sendTeamJoinRejected($request['user_email'], $request['user_username'], $request['team_name']);

        echo json_encode(['success' => true, 'message' => 'Solicitud rechazada']);
    }

    public function getTeam($id) {
        return $this->db->fetch("SELECT t.*, u.username as captain_name FROM teams t JOIN users u ON t.captain_id = u.id WHERE t.id = ?", [$id]);
    }

    public function getTeamMembers($teamId) {
        return $this->db->fetchAll(
            "SELECT tm.*, u.username, u.avatar, u.email FROM team_members tm JOIN users u ON tm.user_id = u.id WHERE tm.team_id = ? ORDER BY tm.is_captain DESC, tm.joined_at ASC",
            [$teamId]
        );
    }

    public function getPendingRequests($teamId) {
        return $this->db->fetchAll(
            "SELECT tjr.*, u.username, u.avatar FROM team_join_requests tjr JOIN users u ON tjr.user_id = u.id WHERE tjr.team_id = ? AND tjr.status = 'pending' ORDER BY tjr.created_at DESC",
            [$teamId]
        );
    }

    public function getUserTeam($userId) {
        return $this->db->fetch(
            "SELECT tm.*, t.id as team_id, t.name as team_name, t.tag, t.logo, t.captain_id FROM team_members tm JOIN teams t ON tm.team_id = t.id WHERE tm.user_id = ?",
            [$userId]
        );
    }

    public function getAllTeams($page = 1, $search = '') {
        $offset = ($page - 1) * ITEMS_PER_PAGE;
        $params = [];
        $where = "WHERE t.is_active = 1";

        if ($search) {
            $where .= " AND (t.name LIKE ? OR t.tag LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $total = $this->db->fetch("SELECT COUNT(*) as total FROM teams t $where", $params)['total'];
        $params[] = ITEMS_PER_PAGE;
        $params[] = $offset;

        $teams = $this->db->fetchAll(
            "SELECT t.*, u.username as captain_name, 
                    (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count,
                    COALESCE((SELECT COUNT(*) FROM tournament_matches WHERE winner_id = t.id AND status = 'completed'), 0) as dyn_wins,
                    COALESCE((SELECT COUNT(*) FROM tournament_matches WHERE loser_id = t.id AND status = 'completed'), 0) as dyn_losses
             FROM teams t 
             JOIN users u ON t.captain_id = u.id 
             $where 
             ORDER BY dyn_wins DESC, t.created_at DESC 
             LIMIT ? OFFSET ?",
            $params
        );
        
        // Override static stats with dynamic ones and calculate points
        foreach ($teams as &$team) {
            $team['wins'] = $team['dyn_wins'];
            $team['losses'] = $team['dyn_losses'];
            $team['points'] = $team['dyn_wins'] * 3;
        }

        return ['teams' => $teams, 'total' => $total, 'pages' => ceil($total / ITEMS_PER_PAGE)];
    }

    public function update($teamId) {
        if (!isLoggedIn()) redirect('login');
        verifyCsrf();

        $team = $this->getTeam($teamId);
        if (!$team || ($team['captain_id'] != currentUserId() && !isAdmin())) {
            setFlash('error', 'No tienes permisos para editar este equipo.');
            redirect('teams');
        }

        $name = sanitize($_POST['name'] ?? $team['name']);
        $tag = sanitize($_POST['tag'] ?? $team['tag']);
        $description = sanitize($_POST['description'] ?? '');
        $region = sanitize($_POST['region'] ?? '');

        $sql = "UPDATE teams SET name = ?, tag = ?, description = ?, region = ?";
        $params = [$name, $tag, $description, $region];

        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logoPath = $this->uploadLogo($_FILES['logo']);
            if ($logoPath) {
                $sql .= ", logo = ?";
                $params[] = $logoPath;
            }
        }

        $sql .= " WHERE id = ?";
        $params[] = $teamId;

        $this->db->update($sql, $params);
        setFlash('success', 'Equipo actualizado correctamente.');
        redirect('teams/view/' . $teamId);
    }

    public function kickMember($userId) {
        if (!isLoggedIn()) {
            echo json_encode(['error' => 'No autorizado']);
            return;
        }
        verifyCsrfRequest();

        // Find the member's team
        $member = $this->db->fetch(
            "SELECT tm.*, t.captain_id, t.name as team_name FROM team_members tm JOIN teams t ON tm.team_id = t.id WHERE tm.user_id = ?",
            [$userId]
        );

        if (!$member) {
            echo json_encode(['error' => 'Miembro no encontrado']);
            return;
        }

        // Only captain can kick
        if ($member['captain_id'] != currentUserId()) {
            echo json_encode(['error' => 'Solo el capitán puede expulsar miembros']);
            return;
        }

        // Cannot kick yourself (captain)
        if ($member['user_id'] == currentUserId()) {
            echo json_encode(['error' => 'No puedes expulsarte a ti mismo']);
            return;
        }

        // Remove member
        $this->db->delete("DELETE FROM team_members WHERE user_id = ? AND team_id = ?", [$userId, $member['team_id']]);

        // Notify kicked user
        $notifCtrl = new NotificationController();
        $notifCtrl->create(
            $userId,
            'team',
            'Expulsado del equipo',
            'Has sido retirado del equipo ' . $member['team_name'],
            'teams'
        );

        echo json_encode(['success' => true, 'message' => 'Miembro expulsado del equipo']);
    }

    public function changeMemberRole($userId) {
        if (!isLoggedIn()) {
            echo json_encode(['error' => 'No autorizado']);
            return;
        }
        verifyCsrfRequest();

        $newRole = $_POST['role'] ?? '';
        $validRoles = array_keys(ML_ROLES);
        if (!in_array($newRole, $validRoles)) {
            echo json_encode(['error' => 'Rol no válido']);
            return;
        }

        // Find the member's team and verify requester is the captain
        $member = $this->db->fetch(
            "SELECT tm.*, t.captain_id, t.name as team_name FROM team_members tm JOIN teams t ON tm.team_id = t.id WHERE tm.user_id = ?",
            [$userId]
        );

        if (!$member) {
            echo json_encode(['error' => 'Miembro no encontrado']);
            return;
        }

        if ($member['captain_id'] != currentUserId()) {
            echo json_encode(['error' => 'Solo el capitán puede cambiar roles']);
            return;
        }

        $this->db->update("UPDATE team_members SET role = ? WHERE user_id = ? AND team_id = ?", [$newRole, $userId, $member['team_id']]);

        // Notify the member
        $notifCtrl = new NotificationController();
        $notifCtrl->create(
            $userId,
            'team',
            'Rol actualizado',
            'El capitán actualizó tu rol a ' . (ML_ROLES[$newRole] ?? $newRole) . ' en ' . $member['team_name'],
            'teams/view/' . $member['team_id']
        );

        echo json_encode(['success' => true, 'newRoleLabel' => ML_ROLES[$newRole] ?? $newRole]);
    }

    private function normalizeRole($role) {
        $validRoles = ['adc', 'mage', 'tank', 'assassin', 'fighter', 'support'];
        $role = strtolower(trim((string) $role));
        return in_array($role, $validRoles, true) ? $role : 'tank';
    }

    private function normalizeLane($lane, $allowNull = true) {
        $lane = strtolower(trim((string) $lane));
        if ($lane === '') {
            return $allowNull ? null : null;
        }

        return array_key_exists($lane, ML_LANES) ? $lane : null;
    }

    private function defaultLaneForRole($role) {
        $roleToLane = [
            'adc' => 'gold',
            'mage' => 'mid',
            'tank' => 'roam',
            'assassin' => 'jungle',
            'fighter' => 'exp',
            'support' => 'roam',
        ];

        return $roleToLane[$role] ?? 'mid';
    }

    private function syncMissingUserMlProfile($userId, $currentProfile, $mlId, $mlServer, $mlNickname) {
        if (!$currentProfile || !$userId) {
            return;
        }

        $updates = [];
        $params = [];

        if (empty($currentProfile['ml_id']) && $mlId !== '') {
            $updates[] = 'ml_id = ?';
            $params[] = $mlId;
        }
        if (empty($currentProfile['ml_server']) && $mlServer !== '') {
            $updates[] = 'ml_server = ?';
            $params[] = $mlServer;
        }
        if (empty($currentProfile['ml_nickname']) && $mlNickname !== '') {
            $updates[] = 'ml_nickname = ?';
            $params[] = $mlNickname;
        }

        if (empty($updates)) {
            return;
        }

        $params[] = $userId;
        $this->db->update('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?', $params);
    }

    private function effectiveTeamMaxMembers($storedMax) {
        $max = (int)$storedMax;
        if ($max <= 0) {
            return 20;
        }
        return max($max, 20);
    }

    private function uploadLogo($file) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowed)) {
            setFlash('error', 'Solo se permiten logos JPG, PNG o WEBP.');
            return null;
        }
        if ($file['size'] > 2 * 1024 * 1024) { // 2MB limit
            setFlash('error', 'El logo no puede pesar más de 2MB.');
            return null;
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'team_' . time() . '_' . uniqid() . '.' . $ext;
        $uploadDir = UPLOAD_PATH . 'teams/';
        
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            return 'teams/' . $filename;
        }
        return null;
    }
}
