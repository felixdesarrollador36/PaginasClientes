<?php
/**
 * Liga WOC - Tournament Controller
 */

class TournamentController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function createTournament($data) {
        return $this->db->insert(
            "INSERT INTO tournaments (name, slug, description, rules, image, season_id, format, team_size, substitutes, max_teams, min_rank, entry_fee, prize_pool, status, registration_start, registration_end, start_date, end_date, is_main_tournament, tournament_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['name'], slugify($data['name']), $data['description'] ?? '', $data['rules'] ?? '',
                $data['image'] ?? null, $data['season_id'] ?? null, $data['format'],
                $data['team_size'] ?? 5, $data['substitutes'] ?? 0, $data['max_teams'] ?? 16, $data['min_rank'] ?? null,
                $data['entry_fee'] ?? 0, $data['prize_pool'] ?? '', $data['status'] ?? 'draft',
                $data['registration_start'] ?? null, $data['registration_end'] ?? null,
                $data['start_date'] ?? null, $data['end_date'] ?? null,
                $data['is_main_tournament'] ?? 0, $data['tournament_type'] ?? null,
                currentUserId()
            ]
        );
    }

    public function updateTournament($id, $data) {
        $sql = "UPDATE tournaments SET name = ?, description = ?, rules = ?, format = ?, team_size = ?, substitutes = ?, max_teams = ?, min_rank = ?, entry_fee = ?, prize_pool = ?, status = ?, registration_start = ?, registration_end = ?, start_date = ?, end_date = ?, stream_url = ?, is_main_tournament = ?, season_id = ?";
        $params = [
            $data['name'], $data['description'] ?? '', $data['rules'] ?? '', $data['format'],
            $data['team_size'] ?? 5, $data['substitutes'] ?? 0, $data['max_teams'] ?? 16, $data['min_rank'] ?? null,
            $data['entry_fee'] ?? 0, $data['prize_pool'] ?? '', $data['status'] ?? 'draft',
            $data['registration_start'] ?? null, $data['registration_end'] ?? null,
            $data['start_date'] ?? null, $data['end_date'] ?? null, $data['stream_url'] ?? null,
            $data['is_main_tournament'] ?? 0, $data['season_id'] ?? null
        ];

        if (!empty($data['image'])) {
            $sql .= ", image = ?";
            $params[] = $data['image'];
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;
        
        // Season lifecycle: handle status changes for main tournaments
        $this->handleSeasonLifecycle($id, $data);
        
        return $this->db->update($sql, $params);
    }

    public function getTournament($id) {
        return $this->db->fetch(
            "SELECT t.*, s.name as season_name, u.username as creator_name,
                    (SELECT COUNT(*) FROM tournament_teams WHERE tournament_id = t.id) as registered_teams
             FROM tournaments t
             LEFT JOIN seasons s ON t.season_id = s.id
             LEFT JOIN users u ON t.created_by = u.id
             WHERE t.id = ?",
            [$id]
        );
    }

    public function getAll($page = 1, $status = null) {
        $offset = ($page - 1) * ITEMS_PER_PAGE;
        $params = [];
        $where = "WHERE 1=1";

        if ($status) {
            $where .= " AND t.status = ?";
            $params[] = $status;
        }

        $total = $this->db->fetch("SELECT COUNT(*) as total FROM tournaments t $where", $params)['total'];
        $params[] = ITEMS_PER_PAGE;
        $params[] = $offset;

        $tournaments = $this->db->fetchAll(
            "SELECT t.*, s.name as season_name,
                    (SELECT COUNT(*) FROM tournament_teams WHERE tournament_id = t.id) as registered_teams
             FROM tournaments t
             LEFT JOIN seasons s ON t.season_id = s.id
             $where
             ORDER BY t.start_date DESC, t.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        );

        return ['tournaments' => $tournaments, 'total' => $total, 'pages' => ceil($total / ITEMS_PER_PAGE)];
    }

    public function getActive() {
        return $this->db->fetchAll(
            "SELECT t.*, (SELECT COUNT(*) FROM tournament_teams WHERE tournament_id = t.id) as registered_teams
             FROM tournaments t
             WHERE t.status IN ('registration', 'ready', 'in_progress')
             ORDER BY t.start_date ASC"
        );
    }

    public function registerTeam($tournamentId) {
        if (!isLoggedIn()) redirect('login');
        verifyCsrf();

        $tournament = $this->getTournament($tournamentId);
        if (!$tournament || !in_array($tournament['status'], ['registration', 'ready'])) {
            setFlash('error', 'Este torneo no está abierto para inscripciones.');
            redirect('tournaments');
        }
        $requiredRosterSize = max(5, (int)($tournament['team_size'] ?? 5));
        $minRosterSize = $requiredRosterSize;
        $substituteSlots = max(0, (int)($tournament['substitutes'] ?? 0));
        $maxRosterSize = $requiredRosterSize + $substituteSlots;

        // Check if user has a team and is captain
        $teamCtrl = new TeamController();
        $userTeam = $teamCtrl->getUserTeam(currentUserId());
        if (!$userTeam || !$userTeam['is_captain']) {
            setFlash('error', 'Debes ser capitán de un equipo para inscribirte.');
            redirect('tournaments/view/' . $tournamentId);
        }

        $teamId = $userTeam['team_id'];

        // Check if team already registered
        $existing = $this->db->fetch(
            "SELECT id FROM tournament_teams WHERE tournament_id = ? AND team_id = ?",
            [$tournamentId, $teamId]
        );
        if ($existing) {
            setFlash('warning', 'Tu equipo ya está inscrito en este torneo.');
            redirect('tournaments/view/' . $tournamentId);
        }

        // Check max teams
        if ($tournament['registered_teams'] >= $tournament['max_teams']) {
            setFlash('error', 'El torneo ya está lleno.');
            redirect('tournaments/view/' . $tournamentId);
        }

        // Check minimum team members
        $members = $teamCtrl->getTeamMembers($teamId);
        if (count($members) < $minRosterSize) {
            setFlash('error', 'Tu equipo necesita al menos ' . $minRosterSize . ' miembros para inscribirse.');
            redirect('tournaments/view/' . $tournamentId);
        }

        // Roster validation - support both main_roster[] and legacy roster[]
        $mainRosterIds = $_POST['main_roster'] ?? [];
        $substituteRosterIds = $_POST['substitute_roster'] ?? [];
        $legacyRosterIds = $_POST['roster'] ?? [];
        
        if (!empty($mainRosterIds) || !empty($substituteRosterIds)) {
            // New format with main and substitutes
            if (!is_array($mainRosterIds) || count($mainRosterIds) < $minRosterSize) {
                setFlash('error', 'Debes seleccionar al menos ' . $minRosterSize . ' jugadores principales.');
                redirect('tournaments/view/' . $tournamentId);
            }
            
            $allRosterIds = array_merge($mainRosterIds, $substituteRosterIds);
            if (count($allRosterIds) > $maxRosterSize) {
                setFlash('error', 'Solo puedes seleccionar hasta ' . $maxRosterSize . ' jugadores en total.');
                redirect('tournaments/view/' . $tournamentId);
            }
            
            $validRoster = array_map('intval', $mainRosterIds);
        } else {
            // Legacy format - single roster array
            $rosterIds = $legacyRosterIds;
            if (!is_array($rosterIds) || count($rosterIds) < $minRosterSize) {
                setFlash('error', 'Debes seleccionar al menos ' . $minRosterSize . ' jugadores para el roster.');
                redirect('tournaments/view/' . $tournamentId);
            }
            if (count($rosterIds) > $maxRosterSize) {
                setFlash('error', 'Solo puedes seleccionar hasta ' . $maxRosterSize . ' jugadores para el roster.');
                redirect('tournaments/view/' . $tournamentId);
            }
            
            $validRoster = array_map('intval', $rosterIds);
        }

        // Verify roster members belong to the team
        $validRoster = [];
        foreach ($members as $m) {
            if (in_array($m['user_id'], $rosterIds ?? $mainRosterIds)) {
                $validRoster[] = (int)$m['user_id'];
            }
        }
        
           if (count($validRoster) < $minRosterSize) {
              setFlash('error', 'Algunos jugadores seleccionados no son válidos o no pertenecen a tu equipo.');
              redirect('tournaments/view/' . $tournamentId);
        }

        // Register
        $paymentStatus = $tournament['entry_fee'] > 0 ? 'pending' : 'waived';
        $rosterJson = json_encode($validRoster);
        $substituteJson = json_encode(array_map('intval', $substituteRosterIds));

        $this->db->insert(
            "INSERT INTO tournament_teams (tournament_id, team_id, roster, substitute_roster, status, payment_status) VALUES (?, ?, ?, ?, 'registered', ?)",
            [$tournamentId, $teamId, $rosterJson, $substituteJson, $paymentStatus]
        );

        // Auto-start logic: Check if tournament is now full
        $currentTeamsCount = $tournament['registered_teams'] + 1;
        if ($currentTeamsCount >= $tournament['max_teams']) {
            $this->db->update(
                "UPDATE tournaments SET status = 'in_progress', start_date = NOW() WHERE id = ?",
                [$tournamentId]
            );
            
            // Auto-generate bracket when it starts
            $this->generateBracket($tournamentId);
            
            setFlash('success', '¡Equipo inscrito exitosamente! El torneo ahora está lleno y ha comenzado automáticamente.');
        } else {
            setFlash('success', '¡Equipo inscrito en el torneo exitosamente!');
        }
        
        redirect('tournaments/view/' . $tournamentId);
    }

    public function leaveTournament($tournamentId) {
        if (!isLoggedIn()) redirect('login');
        verifyCsrf();

        $tournament = $this->getTournament($tournamentId);
        if (!$tournament) {
            setFlash('error', 'Torneo no encontrado.');
            redirect('tournaments');
        }

        // Solo se permite retirar equipo mientras las inscripciones esten abiertas
        if (!in_array($tournament['status'], ['registration', 'ready'], true)) {
            setFlash('error', 'No puedes salir del torneo porque ya está en curso o finalizado.');
            redirect('tournaments/view/' . $tournamentId);
        }

        $teamCtrl = new TeamController();
        $userTeam = $teamCtrl->getUserTeam(currentUserId());
        if (!$userTeam || !$userTeam['is_captain']) {
            setFlash('error', 'Solo el capitán del equipo puede retirarlo del torneo.');
            redirect('tournaments/view/' . $tournamentId);
        }

        $teamId = (int)$userTeam['team_id'];
        $registration = $this->db->fetch(
            "SELECT id FROM tournament_teams WHERE tournament_id = ? AND team_id = ?",
            [$tournamentId, $teamId]
        );

        if (!$registration) {
            setFlash('warning', 'Tu equipo no está inscrito en este torneo.');
            redirect('tournaments/view/' . $tournamentId);
        }

        $this->db->delete("DELETE FROM tournament_teams WHERE id = ?", [$registration['id']]);
        setFlash('success', 'Tu equipo salió del torneo correctamente.');
        redirect('tournaments/view/' . $tournamentId);
    }

    public function generateBracket($tournamentId) {
        $tournament = $this->getTournament($tournamentId);
        if (!$tournament) return false;

        $teams = $this->db->fetchAll(
            "SELECT tt.*, t.name as team_name, t.logo as team_logo
             FROM tournament_teams tt
             JOIN teams t ON tt.team_id = t.id
             WHERE tt.tournament_id = ? AND tt.status = 'registered'
             ORDER BY RAND()",
            [$tournamentId]
        );

        switch ($tournament['format']) {
            case 'single_elimination':
                return $this->generateSingleElimination($tournamentId, $teams);
            case 'double_elimination':
                return $this->generateDoubleElimination($tournamentId, $teams);
            case 'group_stage':
                return $this->generateGroupStage($tournamentId, $teams);
            case 'round_robin':
                return $this->generateRoundRobin($tournamentId, $teams);
        }
        return false;
    }

    private function generateSingleElimination($tournamentId, $teams) {
        // Eliminar matches existentes para evitar duplicados
        $this->db->delete("DELETE FROM tournament_matches WHERE tournament_id = ?", [$tournamentId]);
        $numTeams = count($teams);
        $rounds = ceil(log($numTeams, 2));
        $bracketSize = pow(2, $rounds);

        // Mezclar equipos aleatoriamente para sorteo diferente cada vez
        shuffle($teams);

        // Para 24 equipos: 8 seeds altos pasan a octavos (BYE), 16 juegan ronda 1
        $byes = $bracketSize - $numTeams; // 32-24=8 BYE
        $seedsWithBye = array_slice($teams, 0, $byes); // 8 equipos con BYE
        $seedsWithoutBye = array_slice($teams, $byes); // 16 restantes

        $matchNumber = 1;
        $ronda1_ids = [];

        // RONDA 1: 16 equipos (8 partidos)
        for ($i = 0; $i < count($seedsWithoutBye); $i += 2) {
            $team1 = $seedsWithoutBye[$i] ?? null;
            $team2 = $seedsWithoutBye[$i+1] ?? null;
            $this->db->insert(
                "INSERT INTO tournament_matches (tournament_id, round, match_number, bracket_type, team1_id, team2_id, status) VALUES (?, 1, ?, 'winners', ?, ?, ?)",
                [
                    $tournamentId, $matchNumber,
                    $team1 ? $team1['team_id'] : null,
                    $team2 ? $team2['team_id'] : null,
                    ($team1 && $team2) ? 'pending' : 'completed'
                ]
            );
            // Guardar IDs de partidos de ronda 1 para asignar ganadores en octavos
            $ronda1_ids[] = $matchNumber;
            $matchNumber++;
        }

        // OCTAVOS: 8 seeds altos (BYE) vs 8 ganadores de ronda 1 (en orden)
        for ($i = 0; $i < $byes; $i++) {
            $this->db->insert(
                "INSERT INTO tournament_matches (tournament_id, round, match_number, bracket_type, team1_id, team2_id, status, notes) VALUES (?, 2, ?, 'winners', ?, NULL, 'pending', ?)",
                [
                    $tournamentId, $matchNumber,
                    $seedsWithBye[$i]['team_id'],
                    'Ganador de R1-Match '.$ronda1_ids[$i]
                ]
            );
            $matchNumber++;
        }

        // Crear partidos vacíos para las siguientes rondas
        for ($round = 3; $round <= $rounds; $round++) {
            $matchesInRound = pow(2, $rounds - $round);
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $this->db->insert(
                    "INSERT INTO tournament_matches (tournament_id, round, match_number, bracket_type, status) VALUES (?, ?, ?, 'winners', 'pending')",
                    [$tournamentId, $round, $matchNumber]
                );
                $matchNumber++;
            }
        }

        return true;
    }

    private function generateDoubleElimination($tournamentId, $teams) {

        $numTeams = count($teams);
        $rounds   = (int) ceil(log($numTeams, 2));
        $bracketSize = (int) pow(2, $rounds);

        // Mezclar equipos aleatoriamente para sorteo diferente cada vez
        shuffle($teams);

        // ── WINNERS BRACKET (same as single-elim) ──────────────────────────
        $matchNumber = 1;
        for ($i = 0; $i < $bracketSize; $i += 2) {
            $team1 = $teams[$i]       ?? null;
            $team2 = $teams[$i + 1]   ?? null;
            $status = ($team1 && $team2) ? 'pending' : 'completed';
            $this->db->insert(
                "INSERT INTO tournament_matches (tournament_id, round, match_number, bracket_type, team1_id, team2_id, status) VALUES (?, 1, ?, 'winners', ?, ?, ?)",
                [$tournamentId, $matchNumber, $team1 ? $team1['team_id'] : null, $team2 ? $team2['team_id'] : null, $status]
            );
            if ($team1 && !$team2) {
                $this->db->update("UPDATE tournament_matches SET winner_id = ?, status = 'completed' WHERE tournament_id = ? AND match_number = ? AND round = 1", [$team1['team_id'], $tournamentId, $matchNumber]);
            }
            $matchNumber++;
        }
        // Empty WB rounds 2..N
        for ($round = 2; $round <= $rounds; $round++) {
            $matchesInRound = (int) pow(2, $rounds - $round);
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $this->db->insert("INSERT INTO tournament_matches (tournament_id, round, match_number, bracket_type, status) VALUES (?, ?, ?, 'winners', 'pending')", [$tournamentId, $round, $matchNumber]);
                $matchNumber++;
            }
        }

        // ── LOSERS BRACKET ──────────────────────────────────────────────────
        // LB uses rounds 100..1XX to avoid conflicts with WB round numbers.
        // LB structure for N WB rounds: 2*(N-1) LB rounds + Grand Final (round 200).
        $lbRounds = 2 * ($rounds - 1);
        $lbMatchNumber = 1000; // separate match_number namespace
        for ($lbRound = 1; $lbRound <= $lbRounds; $lbRound++) {
            // Matches per LB round: same pattern as standard double-elim
            $matchesInLbRound = (int) max(1, pow(2, $rounds - 1 - (int) ceil($lbRound / 2)));
            for ($m = 0; $m < $matchesInLbRound; $m++) {
                $this->db->insert(
                    "INSERT INTO tournament_matches (tournament_id, round, match_number, bracket_type, status) VALUES (?, ?, ?, 'losers', 'pending')",
                    [$tournamentId, 100 + $lbRound, $lbMatchNumber]
                );
                $lbMatchNumber++;
            }
        }

        // ── GRAND FINAL ─────────────────────────────────────────────────────
        $this->db->insert(
            "INSERT INTO tournament_matches (tournament_id, round, match_number, bracket_type, status) VALUES (?, 200, ?, 'grand_final', 'pending')",
            [$tournamentId, $lbMatchNumber]
        );

        return true;
    }


    private function generateGroupStage($tournamentId, $teams) {
        $numGroups = max(2, ceil(count($teams) / 4));
        $groups = array_fill(0, $numGroups, []);

        // Distribute teams to groups
        foreach ($teams as $i => $team) {
            $groupIdx = $i % $numGroups;
            $groups[$groupIdx][] = $team;
            $groupName = chr(65 + $groupIdx); // A, B, C...
            $this->db->update(
                "UPDATE tournament_teams SET group_name = ? WHERE tournament_id = ? AND team_id = ?",
                [$groupName, $tournamentId, $team['team_id']]
            );
        }

        // Generate round-robin within each group
        $matchNumber = 1;
        foreach ($groups as $gIdx => $groupTeams) {
            $groupName = chr(65 + $gIdx);
            for ($i = 0; $i < count($groupTeams); $i++) {
                for ($j = $i + 1; $j < count($groupTeams); $j++) {
                    $this->db->insert(
                        "INSERT INTO tournament_matches (tournament_id, round, match_number, bracket_type, group_name, team1_id, team2_id, status) VALUES (?, 1, ?, 'group', ?, ?, ?, 'pending')",
                        [$tournamentId, $matchNumber, $groupName, $groupTeams[$i]['team_id'], $groupTeams[$j]['team_id']]
                    );
                    $matchNumber++;
                }
            }
        }

        return true;
    }

    private function generateRoundRobin($tournamentId, $teams) {
        $numTeams = count($teams);
        if ($numTeams < 2) return false;

        // Round-robin scheduling: each team plays every other team once
        // Distribute matches evenly across rounds
        $matchNumber = 1;
        $totalRounds = ($numTeams % 2 === 0) ? $numTeams - 1 : $numTeams;
        $matchesPerRound = floor($numTeams / 2);

        // Build list of team IDs for scheduling
        $teamIds = array_map(fn($t) => $t['team_id'], $teams);

        // If odd number of teams, add a null "bye" team
        if ($numTeams % 2 !== 0) {
            $teamIds[] = null;
            $numTeams++;
        }

        // Standard round-robin algorithm: fix first team, rotate the rest
        for ($round = 1; $round <= $totalRounds; $round++) {
            for ($m = 0; $m < $numTeams / 2; $m++) {
                $home = $teamIds[$m];
                $away = $teamIds[$numTeams - 1 - $m];

                // Skip bye matches (null team)
                if ($home === null || $away === null) continue;

                $this->db->insert(
                    "INSERT INTO tournament_matches (tournament_id, round, match_number, bracket_type, team1_id, team2_id, status) VALUES (?, ?, ?, 'round_robin', ?, ?, 'pending')",
                    [$tournamentId, $round, $matchNumber, $home, $away]
                );
                $matchNumber++;
            }

            // Rotate: keep first team fixed, shift others
            $last = array_pop($teamIds);
            array_splice($teamIds, 1, 0, [$last]);
        }

        return true;
    }

    public function submitResult($matchId) {
        verifyCsrf();
        
        $match = $this->db->fetch("SELECT * FROM tournament_matches WHERE id = ?", [$matchId]);
        if (!$match) { setFlash('error', 'Partida no encontrada.'); redirect('tournaments'); return; }

        $team1Score = intval($_POST['team1_score'] ?? 0);
        $team2Score = intval($_POST['team2_score'] ?? 0);
        $team1Kills = intval($_POST['team1_kills'] ?? 0);
        $team2Kills = intval($_POST['team2_kills'] ?? 0);
        $team1Towers = intval($_POST['team1_towers'] ?? 0);
        $team2Towers = intval($_POST['team2_towers'] ?? 0);
        $mvpUserId = !empty($_POST['mvp_user_id']) ? intval($_POST['mvp_user_id']) : null;

        // Reject draws — Mobile Legends matches must have a winner
        if ($team1Score === $team2Score) {
            setFlash('error', 'No puede haber empate. Debe haber un ganador.');
            redirect('tournaments/view/' . $match['tournament_id']);
            return;
        }

        // Handle screenshot
        $screenshot = null;
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $screenshot = $this->uploadScreenshot($_FILES['screenshot']);
        }

        // Determine winner/loser
        $winnerId = $team1Score > $team2Score ? $match['team1_id'] : $match['team2_id'];
        $loserId = $team1Score > $team2Score ? $match['team2_id'] : $match['team1_id'];

        // Save match result with kills/towers
        $this->db->insert(
            "INSERT INTO match_results (match_id, team1_score, team2_score, team1_kills, team2_kills, team1_towers, team2_towers, mvp_user_id, screenshot, submitted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$matchId, $team1Score, $team2Score, $team1Kills, $team2Kills, $team1Towers, $team2Towers, $mvpUserId, $screenshot, currentUserId()]
        );

        // Update match scores, winner/loser, and mark completed
        $this->db->update(
            "UPDATE tournament_matches SET team1_score = ?, team2_score = ?, winner_id = ?, loser_id = ?, completed_at = NOW(), status = 'completed' WHERE id = ?",
            [$team1Score, $team2Score, $winnerId, $loserId, $matchId]
        );

        // Advance winner in bracket and check tournament completion
        $this->advanceWinner($matchId, $winnerId);

        setFlash('success', 'Resultado enviado correctamente.');
        redirect('tournaments/view/' . $match['tournament_id']);
    }

    public function getMatches($tournamentId, $round = null) {
        $params = [$tournamentId];
        $where = "WHERE tm.tournament_id = ?";
        
        if ($round) {
            $where .= " AND tm.round = ?";
            $params[] = $round;
        }

        return $this->db->fetchAll(
            "SELECT tm.*, 
                    t1.name as team1_name, t1.logo as team1_logo,
                    t2.name as team2_name, t2.logo as team2_logo,
                    w.name as winner_name
             FROM tournament_matches tm
             LEFT JOIN teams t1 ON tm.team1_id = t1.id
             LEFT JOIN teams t2 ON tm.team2_id = t2.id
             LEFT JOIN teams w ON tm.winner_id = w.id
             $where
             ORDER BY tm.round ASC, tm.match_number ASC",
            $params
        );
    }

    public function getBracketData($tournamentId) {
        $matches = $this->getMatches($tournamentId);
        $tournament = $this->getTournament($tournamentId);
        echo json_encode(['tournament' => $tournament, 'matches' => $matches]);
    }

    public function getRegisteredTeams($tournamentId) {
        $teams = $this->db->fetchAll(
            "SELECT tt.*, t.name as team_name, t.logo as team_logo, t.tag as team_tag,
                    u.username as captain_name
             FROM tournament_teams tt
             JOIN teams t ON tt.team_id = t.id
             JOIN users u ON t.captain_id = u.id
             WHERE tt.tournament_id = ?
             ORDER BY tt.registered_at ASC",
            [$tournamentId]
        );
        
        // If the viewer has correct privileges, attach the roster data with main/substitute distinction
        if (isAdmin() || isSuperAdmin() || isModerator()) {
            foreach ($teams as &$t) {
                $t['roster_data'] = [];
                $allPlayerIds = [];
                
                $mainPlayerIds = [];
                if (!empty($t['roster'])) {
                    $mainPlayerIds = json_decode($t['roster'], true) ?: [];
                    $allPlayerIds = array_merge($allPlayerIds, $mainPlayerIds);
                }
                
                $subPlayerIds = [];
                if (!empty($t['substitute_roster'])) {
                    $subPlayerIds = json_decode($t['substitute_roster'], true) ?: [];
                    $allPlayerIds = array_merge($allPlayerIds, $subPlayerIds);
                }
                
                if (!empty($allPlayerIds)) {
                    $placeholders = implode(',', array_fill(0, count($allPlayerIds), '?'));
                    $query = "SELECT id, username, email, ml_nickname, ml_id, ml_server, whatsapp, phone_brand, discord FROM users WHERE id IN ($placeholders)";
                    $players = $this->db->fetchAll($query, $allPlayerIds);
                    
                    // Mark each player as main or substitute
                    foreach ($players as &$player) {
                        $player['is_substitute'] = in_array($player['id'], $subPlayerIds);
                    }
                    
                    // Sort: main players first, then substitutes
                    usort($players, function($a, $b) {
                        if ($a['is_substitute'] === $b['is_substitute']) return 0;
                        return $a['is_substitute'] ? 1 : -1;
                    });
                    
                    $t['roster_data'] = $players;
                }
            }
        }
        
        return $teams;
    }


    public function advanceWinner($matchId, $winnerId) {
        $match = $this->db->fetch("SELECT * FROM tournament_matches WHERE id = ?", [$matchId]);
        if (!$match) return false;

        $tournament = $this->getTournament($match['tournament_id']);
        
        // Handle Single Elimination logic dynamically
        if ($tournament['format'] === 'single_elimination' || $tournament['format'] === 'double_elimination') {
            // Find matches in the current round
            $currentRoundMatches = $this->db->fetchAll(
                "SELECT id FROM tournament_matches WHERE tournament_id = ? AND round = ? AND bracket_type = 'winners' ORDER BY match_number ASC",
                [$match['tournament_id'], $match['round']]
            );
            
            // Find the index of this match in its round
            $matchIndex = 0;
            foreach ($currentRoundMatches as $idx => $m) {
                if ($m['id'] == $matchId) {
                    $matchIndex = $idx; break;
                }
            }
            
            // Next round match index is half of current (floor)
            $nextMatchIndex = floor($matchIndex / 2);
            $isTeam1 = ($matchIndex % 2 == 0);
            
            // Find next round matches
            $nextRoundMatches = $this->db->fetchAll(
                "SELECT id FROM tournament_matches WHERE tournament_id = ? AND round = ? AND bracket_type = 'winners' ORDER BY match_number ASC",
                [$match['tournament_id'], $match['round'] + 1]
            );
            
            if (isset($nextRoundMatches[$nextMatchIndex])) {
                $nextMatchId = $nextRoundMatches[$nextMatchIndex]['id'];
                if ($isTeam1) {
                    $this->db->update("UPDATE tournament_matches SET team1_id = ? WHERE id = ?", [$winnerId, $nextMatchId]);
                } else {
                    $this->db->update("UPDATE tournament_matches SET team2_id = ? WHERE id = ?", [$winnerId, $nextMatchId]);
                }
            }
        }
        
        // For group_stage: check if all GROUP matches are done, then generate playoffs
        if ($tournament['format'] === 'group_stage') {
            $pendingGroup = $this->db->fetch(
                "SELECT COUNT(*) as cnt FROM tournament_matches WHERE tournament_id = ? AND bracket_type = 'group' AND status != 'completed'",
                [$match['tournament_id']]
            )['cnt'];
            
            if ($pendingGroup == 0) {
                // Check if playoffs already exist
                $playoffExists = $this->db->fetch(
                    "SELECT COUNT(*) as cnt FROM tournament_matches WHERE tournament_id = ? AND bracket_type = 'winners'",
                    [$match['tournament_id']]
                )['cnt'];
                
                if ($playoffExists == 0) {
                    $this->generateGroupStagePlayoffs($match['tournament_id']);
                }
            }
            
            // Handle advancement within knockout rounds (if match is a 'winners' bracket)
            if ($match['bracket_type'] === 'winners') {
                $currentRoundMatches = $this->db->fetchAll(
                    "SELECT id FROM tournament_matches WHERE tournament_id = ? AND round = ? AND bracket_type = 'winners' ORDER BY match_number ASC",
                    [$match['tournament_id'], $match['round']]
                );
                $matchIndex = 0;
                foreach ($currentRoundMatches as $idx => $m) {
                    if ($m['id'] == $matchId) { $matchIndex = $idx; break; }
                }
                $nextMatchIndex = floor($matchIndex / 2);
                $isTeam1 = ($matchIndex % 2 == 0);
                $nextRoundMatches = $this->db->fetchAll(
                    "SELECT id FROM tournament_matches WHERE tournament_id = ? AND round = ? AND bracket_type = 'winners' ORDER BY match_number ASC",
                    [$match['tournament_id'], $match['round'] + 1]
                );
                if (isset($nextRoundMatches[$nextMatchIndex])) {
                    $nextMatchId = $nextRoundMatches[$nextMatchIndex]['id'];
                    if ($isTeam1) {
                        $this->db->update("UPDATE tournament_matches SET team1_id = ? WHERE id = ?", [$winnerId, $nextMatchId]);
                    } else {
                        $this->db->update("UPDATE tournament_matches SET team2_id = ? WHERE id = ?", [$winnerId, $nextMatchId]);
                    }
                }
            }
        }
        
        // Check if tournament is finished (ALL matches including knockouts)
        $pending = $this->db->fetch("SELECT COUNT(*) as pending FROM tournament_matches WHERE tournament_id = ? AND status != 'completed'", [$match['tournament_id']])['pending'];
        if ($pending == 0) {
            $this->db->update("UPDATE tournaments SET status = 'completed', end_date = NOW() WHERE id = ?", [$match['tournament_id']]);
            $this->db->update("UPDATE tournament_teams SET status = 'winner' WHERE tournament_id = ? AND team_id = ?", [$match['tournament_id'], $winnerId]);
        }
        
        return true;
    }

    /**
     * Generate knockout playoffs from group stage results.
     * Takes top 2 teams from each group (ranked by wins, then kill diff) and creates single-elimination bracket.
     */
    public function generateGroupStagePlayoffs($tournamentId) {
        // Get all groups
        $groups = $this->db->fetchAll(
            "SELECT DISTINCT group_name FROM tournament_teams WHERE tournament_id = ? AND group_name IS NOT NULL ORDER BY group_name",
            [$tournamentId]
        );
        
        $qualifiedTeams = [];
        
        foreach ($groups as $g) {
            $groupName = $g['group_name'];
            
            // Rank teams within this group based on completed match results
            $teamStats = $this->db->fetchAll(
                "SELECT t.id, t.name,
                    SUM(CASE 
                        WHEN (m.team1_id = t.id AND r.team1_score > r.team2_score) OR (m.team2_id = t.id AND r.team2_score > r.team1_score) THEN 1 
                        ELSE 0 END) as wins,
                    SUM(CASE 
                        WHEN m.team1_id = t.id THEN r.team1_kills - r.team2_kills
                        WHEN m.team2_id = t.id THEN r.team2_kills - r.team1_kills
                        ELSE 0 END) as kill_diff
                FROM tournament_teams tt
                JOIN teams t ON tt.team_id = t.id
                JOIN tournament_matches m ON m.tournament_id = tt.tournament_id AND (m.team1_id = t.id OR m.team2_id = t.id) AND m.bracket_type = 'group' AND m.group_name = ?
                JOIN match_results r ON r.match_id = m.id
                WHERE tt.tournament_id = ? AND tt.group_name = ?
                GROUP BY t.id
                ORDER BY wins DESC, kill_diff DESC",
                [$groupName, $tournamentId, $groupName]
            );
            
            // Take top 2
            for ($i = 0; $i < min(2, count($teamStats)); $i++) {
                $qualifiedTeams[] = [
                    'team_id' => $teamStats[$i]['id'],
                    'team_name' => $teamStats[$i]['name'],
                    'group' => $groupName,
                    'seed' => $i + 1  // 1st or 2nd place
                ];
            }
        }
        
        // Now create single-elimination bracket from qualified teams
        // Seed: 1st of Group A vs 2nd of Group B, etc. (FIFA-style crossover)
        $numTeams = count($qualifiedTeams);
        if ($numTeams < 2) return false;
        
        // Separate 1st and 2nd place teams
        $firsts = array_filter($qualifiedTeams, fn($t) => $t['seed'] == 1);
        $seconds = array_filter($qualifiedTeams, fn($t) => $t['seed'] == 2);
        $firsts = array_values($firsts);
        $seconds = array_values(array_reverse($seconds)); // Reverse to cross-match
        
        // Pair them: 1st[0] vs 2nd[last], 1st[1] vs 2nd[last-1], etc.
        $pairs = [];
        for ($i = 0; $i < count($firsts); $i++) {
            $opponent = $seconds[$i] ?? null;
            $pairs[] = [$firsts[$i], $opponent];
        }
        
        // Generate rounds
        $numKnockoutTeams = count($pairs) * 2;
        $numRounds = ceil(log($numKnockoutTeams, 2));
        $matchNumber = 1000; // Start high to avoid conflicts with group match numbers
        
        // Round 1 of knockouts (Round of 16 for 32 teams = 16 qualified)
        $knockoutRound = 100; // Use high round numbers to distinguish from group stage
        for ($r = 0; $r < $numRounds; $r++) {
            $matchesInRound = $numKnockoutTeams / pow(2, $r + 1);
            for ($m = 0; $m < $matchesInRound; $m++) {
                $t1 = null;
                $t2 = null;
                
                // Only fill teams for the first round
                if ($r == 0 && isset($pairs[$m])) {
                    $t1 = $pairs[$m][0]['team_id'] ?? null;
                    $t2 = $pairs[$m][1]['team_id'] ?? null;
                }
                
                $this->db->insert(
                    "INSERT INTO tournament_matches (tournament_id, round, match_number, bracket_type, team1_id, team2_id, status) VALUES (?, ?, ?, 'winners', ?, ?, 'pending')",
                    [$tournamentId, $knockoutRound + $r, $matchNumber++, $t1, $t2]
                );
            }
        }
        
        return true;
    }

    /**
     * Delete a tournament and all its related data (CASCADE handles matches, results, stats, etc.)
     */
    public function deleteTournament($id) {
        $tournament = $this->getTournament($id);
        if (!$tournament) return false;

        // Delete tournament image if exists
        if (!empty($tournament['image'])) {
            $imgPath = UPLOAD_PATH . $tournament['image'];
            if (file_exists($imgPath)) {
                unlink($imgPath);
            }
        }

        // CASCADE will handle: tournament_teams, tournament_matches -> match_results, match_player_stats, prizes
        $this->db->update("DELETE FROM tournaments WHERE id = ?", [$id]);
        return true;
    }

    /**
     * Create a preset LIGA WOC tournament.
     * Uses the LIGA_WOC_PRESET constant, auto-links to the current active season.
     */
    public function createPresetTournament() {
        $preset = LIGA_WOC_PRESET;
        
        // Get or create the active season
        $activeSeason = $this->db->fetch("SELECT id, name FROM seasons WHERE is_active = 1 LIMIT 1");
        
        if (!$activeSeason) {
            // Count existing seasons to determine the next number
            $seasonCount = $this->db->fetch("SELECT COUNT(*) as cnt FROM seasons")['cnt'];
            $seasonNum = $seasonCount + 1;
            $seasonName = 'Temporada ' . $seasonNum;
            
            $this->db->insert(
                "INSERT INTO seasons (name, start_date, is_active, description) VALUES (?, CURDATE(), 1, ?)",
                [$seasonName, 'Temporada ' . $seasonNum . ' de Liga WOC']
            );
            $activeSeason = $this->db->fetch("SELECT id, name FROM seasons WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        }
        
        $data = [
            'name' => $preset['name'] . ' - ' . $activeSeason['name'],
            'description' => $preset['description'],
            'rules' => $preset['rules'],
            'format' => $preset['format'],
            'team_size' => $preset['team_size'],
            'max_teams' => $preset['max_teams'],
            'is_main_tournament' => $preset['is_main_tournament'],
            'tournament_type' => $preset['tournament_type'],
            'season_id' => $activeSeason['id'],
            'status' => 'registration',
        ];
        
        return $this->createTournament($data);
    }

    /**
     * Get group standings for a group_stage tournament.
     * Returns an array keyed by group name, each with sorted team standings.
     */
    public function getGroupStandings($tournamentId) {
        // Get all groups
        $groups = $this->db->fetchAll(
            "SELECT DISTINCT group_name FROM tournament_teams WHERE tournament_id = ? AND group_name IS NOT NULL ORDER BY group_name",
            [$tournamentId]
        );
        
        $standings = [];
        
        foreach ($groups as $g) {
            $groupName = $g['group_name'];
            
            $teamStats = $this->db->fetchAll(
                "SELECT t.id, t.name, t.logo, t.tag,
                    COUNT(DISTINCT m.id) as matches_played,
                    SUM(CASE WHEN m.winner_id = t.id THEN 1 ELSE 0 END) as wins,
                    SUM(CASE WHEN m.loser_id = t.id THEN 1 ELSE 0 END) as losses,
                    SUM(CASE WHEN m.status = 'completed' AND m.winner_id IS NULL THEN 1 ELSE 0 END) as draws,
                    SUM(CASE WHEN m.winner_id = t.id THEN 1 ELSE 0 END) * " . POINTS_WIN . " as points,
                    COALESCE(SUM(CASE 
                        WHEN m.team1_id = t.id THEN COALESCE(r.team1_kills, 0) - COALESCE(r.team2_kills, 0)
                        WHEN m.team2_id = t.id THEN COALESCE(r.team2_kills, 0) - COALESCE(r.team1_kills, 0)
                        ELSE 0 END), 0) as kill_diff,
                    COALESCE(SUM(CASE 
                        WHEN m.team1_id = t.id THEN COALESCE(r.team1_towers, 0) - COALESCE(r.team2_towers, 0)
                        WHEN m.team2_id = t.id THEN COALESCE(r.team2_towers, 0) - COALESCE(r.team1_towers, 0)
                        ELSE 0 END), 0) as tower_diff
                FROM tournament_teams tt
                JOIN teams t ON tt.team_id = t.id
                LEFT JOIN tournament_matches m ON m.tournament_id = tt.tournament_id 
                    AND (m.team1_id = t.id OR m.team2_id = t.id) 
                    AND m.bracket_type = 'group' 
                    AND m.group_name = ?
                    AND m.status = 'completed'
                LEFT JOIN match_results r ON r.match_id = m.id
                WHERE tt.tournament_id = ? AND tt.group_name = ?
                GROUP BY t.id
                ORDER BY points DESC, kill_diff DESC, tower_diff DESC, wins DESC",
                [$groupName, $tournamentId, $groupName]
            );
            
            $standings[$groupName] = $teamStats;
        }
        
        return $standings;
    }

    /**
     * Handle season lifecycle when a main tournament status changes.
     * - in_progress: ensure a season is active
     * - completed: end the current season
     */
    private function handleSeasonLifecycle($tournamentId, $data) {
        // Only for main tournaments
        if (empty($data['is_main_tournament'])) return;
        
        $currentTournament = $this->getTournament($tournamentId);
        if (!$currentTournament) return;
        
        $newStatus = $data['status'] ?? null;
        $oldStatus = $currentTournament['status'];
        
        if ($newStatus === $oldStatus) return;
        
        // When tournament starts
        if ($newStatus === 'in_progress' && $oldStatus !== 'in_progress') {
            // Ensure the linked season is active
            $seasonId = $data['season_id'] ?? $currentTournament['season_id'];
            if ($seasonId) {
                $this->db->update("UPDATE seasons SET is_active = 1, start_date = CURDATE() WHERE id = ? AND is_active = 0", [$seasonId]);
            }
        }
        
        // When tournament completes
        if ($newStatus === 'completed' && $oldStatus !== 'completed') {
            $seasonId = $data['season_id'] ?? $currentTournament['season_id'];
            if ($seasonId) {
                $this->db->update("UPDATE seasons SET is_active = 0, end_date = CURDATE() WHERE id = ?", [$seasonId]);
            }
        }
    }

    private function uploadScreenshot($file) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowed)) return null;
        if ($file['size'] > MAX_UPLOAD_SIZE) return null;

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'match_' . time() . '_' . uniqid() . '.' . $ext;
        $uploadDir = UPLOAD_PATH . 'matches/';
        
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            return 'matches/' . $filename;
        }
        return null;
    }
}
