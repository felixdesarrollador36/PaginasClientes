<?php
// LIGAWOC/api/transfer_leadership.php
require_once __DIR__ . '/../controllers/TeamController.php';

// Obtener el teamId por GET (?team_id=...)
$teamId = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;

if (!$teamId) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta team_id en la URL']);
    exit;
}

$controller = new TeamController();
$controller->transferLeadership($teamId);
