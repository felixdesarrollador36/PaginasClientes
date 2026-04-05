<?php
/**
 * Liga WOC - Notification Controller
 */

class NotificationController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($userId, $type, $title, $message, $link = null) {
        return $this->db->insert(
            "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)",
            [$userId, $type, $title, $message, $link]
        );
    }

    public function getNotifications() {
        if (!isLoggedIn()) {
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $notifications = $this->db->fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
            [currentUserId()]
        );
        echo json_encode(['notifications' => $notifications]);
    }

    public function getUnreadCount() {
        if (!isLoggedIn()) {
            echo json_encode(['count' => 0]);
            return;
        }

        $count = $this->db->fetch(
            "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
            [currentUserId()]
        )['count'];
        echo json_encode(['count' => $count]);
    }

    public function markAsRead($id) {
        if (!isLoggedIn()) {
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        if ($id === 'all') {
            $this->db->update(
                "UPDATE notifications SET is_read = 1 WHERE user_id = ?",
                [currentUserId()]
            );
        } else {
            $this->db->update(
                "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?",
                [$id, currentUserId()]
            );
        }
        echo json_encode(['success' => true]);
    }

    public function getUserNotifications($userId, $limit = 20) {
        return $this->db->fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Notify all members of a team
     */
    public function notifyTeamMembers($teamId, $type, $title, $message, $link = null) {
        $members = $this->db->fetchAll("SELECT user_id FROM team_members WHERE team_id = ?", [$teamId]);
        foreach ($members as $m) {
            $this->create($m['user_id'], $type, $title, $message, $link);
        }
    }

    /**
     * Auto-notify when a match is scheduled
     */
    public function notifyMatchScheduled($matchId) {
        $match = $this->db->fetch(
            "SELECT m.*, t1.name as t1_name, t2.name as t2_name, tr.name as tournament_name
             FROM tournament_matches m
             LEFT JOIN teams t1 ON m.team1_id = t1.id
             LEFT JOIN teams t2 ON m.team2_id = t2.id
             LEFT JOIN tournaments tr ON m.tournament_id = tr.id
             WHERE m.id = ?", [$matchId]
        );
        if (!$match || !$match['team1_id'] || !$match['team2_id']) return;

        $dateStr = $match['scheduled_at'] ? date('d M Y, H:i', strtotime($match['scheduled_at'])) : 'Fecha por confirmar';
        $link = url('tournaments/view/' . $match['tournament_id']);

        $this->notifyTeamMembers($match['team1_id'], 'match_scheduled',
            '📅 Partida Programada',
            "Tu partida vs {$match['t2_name']} en {$match['tournament_name']} ha sido programada para {$dateStr}.",
            $link
        );
        $this->notifyTeamMembers($match['team2_id'], 'match_scheduled',
            '📅 Partida Programada',
            "Tu partida vs {$match['t1_name']} en {$match['tournament_name']} ha sido programada para {$dateStr}.",
            $link
        );
    }

    /**
     * Auto-notify when match results are submitted
     */
    public function notifyMatchCompleted($matchId) {
        $match = $this->db->fetch(
            "SELECT m.*, t1.name as t1_name, t2.name as t2_name, w.name as winner_name, tr.name as tournament_name
             FROM tournament_matches m
             LEFT JOIN teams t1 ON m.team1_id = t1.id
             LEFT JOIN teams t2 ON m.team2_id = t2.id
             LEFT JOIN teams w ON m.winner_id = w.id
             LEFT JOIN tournaments tr ON m.tournament_id = tr.id
             WHERE m.id = ?", [$matchId]
        );
        if (!$match) return;

        $link = url('match-history');
        $score = "{$match['team1_score']}-{$match['team2_score']}";
        
        // Notify winner
        if ($match['winner_id'] == $match['team1_id']) {
            $this->notifyTeamMembers($match['team1_id'], 'match_result',
                '🏆 ¡Victoria!',
                "Ganaron vs {$match['t2_name']} ({$score}) en {$match['tournament_name']}.",
                $link
            );
            $this->notifyTeamMembers($match['team2_id'], 'match_result',
                '⚔️ Resultado de Partida',
                "Perdieron vs {$match['t1_name']} ({$score}) en {$match['tournament_name']}.",
                $link
            );
        } else {
            $this->notifyTeamMembers($match['team2_id'], 'match_result',
                '🏆 ¡Victoria!',
                "Ganaron vs {$match['t1_name']} ({$score}) en {$match['tournament_name']}.",
                $link
            );
            $this->notifyTeamMembers($match['team1_id'], 'match_result',
                '⚔️ Resultado de Partida',
                "Perdieron vs {$match['t2_name']} ({$score}) en {$match['tournament_name']}.",
                $link
            );
        }
    }

    /**
     * Notify team they advanced in the bracket
     */
    public function notifyBracketAdvance($winnerId, $tournamentId) {
        $tournament = $this->db->fetch("SELECT name FROM tournaments WHERE id = ?", [$tournamentId]);
        if (!$tournament) return;

        $link = url('tournaments/view/' . $tournamentId);
        $this->notifyTeamMembers($winnerId, 'bracket_advance',
            '🚀 ¡Avanzaron en el Bracket!',
            "Su equipo avanzó a la siguiente ronda en {$tournament['name']}.",
            $link
        );
    }
}
