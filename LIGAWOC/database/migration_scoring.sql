-- Migration: Add kills/towers diff tracking for scoring system
-- Win = 3 pts, Loss = 0 pts, Diff (kills + towers) for tiebreakers

-- Add kills/towers to match_results
ALTER TABLE match_results ADD COLUMN team1_kills INT DEFAULT 0 AFTER team2_score;
ALTER TABLE match_results ADD COLUMN team2_kills INT DEFAULT 0 AFTER team1_kills;
ALTER TABLE match_results ADD COLUMN team1_towers INT DEFAULT 0 AFTER team2_kills;
ALTER TABLE match_results ADD COLUMN team2_towers INT DEFAULT 0 AFTER team1_towers;

-- Add kills_diff and towers_diff to team_rankings
ALTER TABLE team_rankings ADD COLUMN kills_diff INT DEFAULT 0 AFTER points;
ALTER TABLE team_rankings ADD COLUMN towers_diff INT DEFAULT 0 AFTER kills_diff;
