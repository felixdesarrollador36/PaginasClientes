<?php
/**
 * Simple .env loader - No dependencies required
 * Usage: require_once __DIR__ . '/env-loader.php';
 */

function loadEnv($path = null) {
    if ($path === null) {
        $path = dirname(dirname(__FILE__)) . '/.env';
    }
    
    if (!file_exists($path)) {
        // En producción, las variables de entorno deben estar en el servidor
        // En desarrollo, intenta cargar desde .env
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parsear KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remover comillas si existen
            if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }
            
            // Establecer en $_ENV si no existe
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
        }
    }
}

/**
 * Obtener variable de entorno
 */
function env($key, $default = null) {
    // Primero intenta variable de entorno del sistema (recomendado en producción)
    $value = getenv($key);
    
    if ($value !== false) {
        return $value;
    }

    // Algunos hostings exponen variables en $_SERVER (SetEnv/.htaccess)
    if (isset($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    
    // Luego intenta $_ENV
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    
    return $default;
}

// Cargar .env al requireir este archivo
loadEnv();
