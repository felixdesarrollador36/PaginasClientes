<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Probando configuración...</h1>";

try {
    require_once __DIR__ . '/config/app.php';
    echo "app.php cargado correctamente.<br>";
} catch (Throwable $e) {
    echo "Error cargando app.php: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine() . "<br>";
}

try {
    require_once __DIR__ . '/config/database.php';
    echo "database.php cargado correctamente.<br>";
} catch (Throwable $e) {
    echo "Error cargando database.php: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine() . "<br>";
}

echo "<h3>Autoloads manuales:</h3>";
try {
    require_once __DIR__ . '/controllers/AuthController.php';
    echo "AuthController OK<br>";
} catch (Throwable $e) {
    echo "Error en AuthController: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine() . "<br>";
}
