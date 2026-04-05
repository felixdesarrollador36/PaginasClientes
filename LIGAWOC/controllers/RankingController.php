<?php
/**
 * Liga WOC - Ranking Controller
 */

class RankingController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get dynamic team rankings based on actual match results.
     * Uses winner_id/loser_id from tournament_matches for reliable win/loss tracking.
     * Calculated: Wins (+3 pts), Kills/Towers diff, Matches Played.
     * 
     * Default (no tournament filter): only counts is_main_tournament=1 tournaments.
     * With tournament filter: counts that specific tournament.
     */
    public function getDynamicTeamRankings($seasonId = null, $tournamentId = null, $limit = 50) {
        $params = [];
        $joinFilters = "";
        
        if ($tournamentId) {
            $joinFilters .= " AND m.tournament_id = ?";
            $params[] = $tournamentId;
        } elseif ($seasonId) {
            $joinFilters .= " AND t_tourn.season_id = ?";
            $params[] = $seasonId;
            // Within a season, default to main tournaments only
            $joinFilters .= " AND t_tourn.is_main_tournament = 1";
        } else {
            // Global: only main tournaments count
            $joinFilters .= " AND t_tourn.is_main_tournament = 1";
        }

        $params[] = $limit;

        $sql = "
            SELECT ranked.* FROM (
                SELECT 
                    t.id, t.name as team_name, t.tag as team_tag, t.logo as team_logo,
                    COUNT(DISTINCT m.id) as matches_played,
                    SUM(CASE WHEN m.winner_id = t.id THEN 1 ELSE 0 END) as wins,
                    SUM(CASE WHEN m.loser_id = t.id THEN 1 ELSE 0 END) as losses,
                    SUM(CASE WHEN m.team1_id = t.id THEN COALESCE(r.team1_kills, 0) - COALESCE(r.team2_kills, 0) 
                             WHEN m.team2_id = t.id THEN COALESCE(r.team2_kills, 0) - COALESCE(r.team1_kills, 0)
                             ELSE 0 END) as kills_diff,
                    SUM(CASE WHEN m.team1_id = t.id THEN COALESCE(r.team1_towers, 0) - COALESCE(r.team2_towers, 0) 
                             WHEN m.team2_id = t.id THEN COALESCE(r.team2_towers, 0) - COALESCE(r.team1_towers, 0)
                             ELSE 0 END) as towers_diff
                FROM teams t
                JOIN tournament_matches m ON (t.id = m.team1_id OR t.id = m.team2_id) AND m.status = 'completed'
                LEFT JOIN match_results r ON m.id = r.match_id
                JOIN tournaments t_tourn ON m.tournament_id = t_tourn.id
                WHERE 1=1 $joinFilters
                GROUP BY t.id
            ) ranked
            ORDER BY 
                ranked.wins DESC, 
                (ranked.kills_diff + ranked.towers_diff) DESC, 
                ranked.matches_played ASC
            LIMIT ?
        ";

        $rankings = $this->db->fetchAll($sql, $params);
        
        // Add points Calculation (+3 per win)
        foreach ($rankings as &$r) {
            $r['points'] = $r['wins'] * 3;
        }
        
        return $rankings;
    }

    /**
     * Get dynamic player rankings based on actual match player stats.
     * Uses winner_id from tournament_matches for reliable win tracking.
     */
    public function getDynamicPlayerRankings($seasonId = null, $tournamentId = null, $limit = 50) {
        $params = [];
        $whereFilters = "";
        
        if ($tournamentId) {
            $whereFilters .= " AND m.tournament_id = ?";
            $params[] = $tournamentId;
        } elseif ($seasonId) {
            $whereFilters .= " AND t_tourn.season_id = ?";
            $params[] = $seasonId;
            $whereFilters .= " AND t_tourn.is_main_tournament = 1";
        } else {
            $whereFilters .= " AND t_tourn.is_main_tournament = 1";
        }

        $params[] = $limit;

        $sql = "
            SELECT ranked.* FROM (
                SELECT 
                    u.id, u.username, u.avatar, u.ml_nickname,
                    tm.role as player_role, t.name as team_name, t.tag as team_tag, t.logo as team_logo,
                    COUNT(mps.id) as matches_played,
                    SUM(mps.kills) as total_kills,
                    SUM(mps.deaths) as total_deaths,
                    SUM(mps.assists) as total_assists,
                    SUM(CASE WHEN m.winner_id = mps.team_id THEN 1 ELSE 0 END) as matches_won
                FROM match_player_stats mps
                JOIN users u ON mps.user_id = u.id
                JOIN tournament_matches m ON mps.match_id = m.id AND m.status = 'completed'
                JOIN tournaments t_tourn ON m.tournament_id = t_tourn.id
                LEFT JOIN team_members tm ON u.id = tm.user_id AND tm.team_id = mps.team_id
                LEFT JOIN teams t ON mps.team_id = t.id
                WHERE 1=1 $whereFilters
                GROUP BY u.id
            ) ranked
            ORDER BY 
                (ranked.total_kills + ranked.total_assists) / GREATEST(ranked.total_deaths, 1) DESC,
                ranked.matches_won DESC,
                ranked.total_kills DESC
            LIMIT ?
        ";

        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get dynamic hero rankings based on actual picks and match results.
     * Uses winner_id from tournament_matches for reliable win tracking.
     */
    public function getHeroRankings($seasonId = null, $tournamentId = null, $limit = 50) {
        $params = [];
        $whereFilters = "";
        
        if ($tournamentId) {
            $whereFilters .= " AND m.tournament_id = ?";
            $params[] = $tournamentId;
        } elseif ($seasonId) {
            $whereFilters .= " AND t_tourn.season_id = ?";
            $params[] = $seasonId;
            $whereFilters .= " AND t_tourn.is_main_tournament = 1";
        } else {
            $whereFilters .= " AND t_tourn.is_main_tournament = 1";
        }
        
        // Exclude empty heroes
        $whereFilters .= " AND mps.hero_used != '' AND mps.hero_used IS NOT NULL";

        $params[] = $limit;

        $sql = "
            SELECT ranked.* FROM (
                SELECT 
                    mps.hero_used as hero_name,
                    COUNT(mps.id) as matches_played,
                    SUM(mps.kills) as total_kills,
                    SUM(mps.deaths) as total_deaths,
                    SUM(mps.assists) as total_assists,
                    SUM(CASE WHEN m.winner_id = mps.team_id THEN 1 ELSE 0 END) as matches_won
                FROM match_player_stats mps
                JOIN tournament_matches m ON mps.match_id = m.id AND m.status = 'completed'
                JOIN tournaments t_tourn ON m.tournament_id = t_tourn.id
                WHERE 1=1 $whereFilters
                GROUP BY mps.hero_used
            ) ranked
            ORDER BY 
                ranked.matches_played DESC,
                ranked.matches_won DESC,
                (ranked.total_kills + ranked.total_assists) / GREATEST(ranked.total_deaths, 1) DESC
            LIMIT ?
        ";

        $rankings = $this->db->fetchAll($sql, $params);
        
        // Add Win Rate percentage
        foreach ($rankings as &$r) {
            $r['win_rate'] = $r['matches_played'] > 0 ? round(($r['matches_won'] / $r['matches_played']) * 100, 1) : 0;
        }
        
        return $rankings;
    }

    public function getSeasons() {
        return $this->db->fetchAll("SELECT * FROM seasons ORDER BY start_date DESC");
    }
    
    public function getActiveTournaments($seasonId = null) {
        if ($seasonId) {
            return $this->db->fetchAll("SELECT id, name, is_main_tournament FROM tournaments WHERE season_id = ? ORDER BY start_date DESC", [$seasonId]);
        }
        return $this->db->fetchAll("SELECT id, name, is_main_tournament FROM tournaments ORDER BY start_date DESC");
    }

    /**
     * Get MVP leaderboard: players with the most MVP awards across match results.
     */
    public function getMvpLeaderboard($seasonId = null, $tournamentId = null, $limit = 30) {
        $params = [];
        $filters = '';

        if ($tournamentId) {
            $filters .= ' AND m.tournament_id = ?';
            $params[] = $tournamentId;
        } elseif ($seasonId) {
            $filters .= ' AND t_tourn.season_id = ? AND t_tourn.is_main_tournament = 1';
            $params[] = $seasonId;
        } else {
            $filters .= ' AND t_tourn.is_main_tournament = 1';
        }

        $params[] = $limit;

        return $this->db->fetchAll("
            SELECT u.id, u.username, u.avatar, u.ml_nickname,
                   tea.name as team_name, tea.tag as team_tag,
                   COUNT(mr.id) as mvp_count,
                   COUNT(DISTINCT m.tournament_id) as tournaments_count
            FROM match_results mr
            JOIN users u ON mr.mvp_user_id = u.id
            JOIN tournament_matches m ON mr.match_id = m.id
            JOIN tournaments t_tourn ON m.tournament_id = t_tourn.id
            LEFT JOIN team_members tm ON u.id = tm.user_id
            LEFT JOIN teams tea ON tm.team_id = tea.id AND tea.is_active = 1
            WHERE mr.mvp_user_id IS NOT NULL $filters
            GROUP BY u.id
            ORDER BY mvp_count DESC, tournaments_count DESC
            LIMIT ?
        ", $params);
    }
}
