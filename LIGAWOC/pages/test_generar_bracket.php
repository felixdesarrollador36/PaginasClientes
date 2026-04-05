<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/TournamentController.php';

$tournamentId = 3; // Cambia por el ID de tu torneo si es diferente
$controller = new TournamentController();
$controller->generateBracket($tournamentId);

echo "Bracket generado para el torneo $tournamentId\n";
