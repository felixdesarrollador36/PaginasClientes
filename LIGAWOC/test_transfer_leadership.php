<?php

// Helpers mínimos
function currentUserId() { return 1; }

// Mock Database
class Database {
    private static $instance;

    public static function getInstance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public function fetch($sql, $params = []) {
        if (stripos($sql, 'FROM teams') !== false) {
            return ['id' => 1, 'name' => 'Equipo Prueba', 'captain_id' => 1];
        }

        if (stripos($sql, 'FROM team_members') !== false) {
            if (($params[1] ?? null) == 1) {
                return ['user_id' => 1, 'is_captain' => 1];
            }
            if (($params[1] ?? null) == 2) {
                return ['user_id' => 2, 'is_captain' => 0];
            }
        }

        return null;
    }

    public function update($sql, $params = []) {
        echo "[DB UPDATE] $sql\n";
        return true;
    }
}

// Controlador
require_once __DIR__ . '/controllers/TeamController.php';

// Test
echo "=== TEST transferLeadership ===\n";

$teamCtrl = new TeamController();
$teamCtrl->transferLeadership(1);

echo "=== FIN ===\n";