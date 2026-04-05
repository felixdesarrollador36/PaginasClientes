<?php
/**
 * Liga WOC - Database Connection
 * PDO wrapper with prepared statements
 */

// Cargar variables de entorno
require_once __DIR__ . '/env-loader.php';

class Database {
    private static $instance = null;
    private $pdo;
    
    // Usar variables de entorno para credenciales
    private function getConfig() {
        $isProduction = env('APP_ENV') === 'production';

        $config = [
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'dbname' => env('DB_NAME', 'ligawoc'),
            'username' => env('DB_USER', env('DB_USERNAME', $isProduction ? null : 'root')),
            'password' => env('DB_PASS', env('DB_PASSWORD', $isProduction ? null : '')),
        ];

        if ($isProduction) {
            $required = ['host' => 'DB_HOST', 'dbname' => 'DB_NAME', 'username' => 'DB_USER/DB_USERNAME', 'password' => 'DB_PASS/DB_PASSWORD'];
            $missing = [];

            foreach ($required as $key => $envName) {
                if (!isset($config[$key]) || $config[$key] === '') {
                    $missing[] = $envName;
                }
            }

            if (!empty($missing)) {
                throw new RuntimeException('Missing DB environment variables in production: ' . implode(', ', $missing));
            }
        }

        return $config;
    }

    private function __construct() {
        $config = $this->getConfig();
        $charset = 'utf8mb4';
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (Throwable $e) {
            // En producción, no exponer detalles de error
            $isProduction = env('APP_ENV') === 'production';
            if ($isProduction) {
                error_log('[DB_ERROR] ' . $e->getMessage());
                die('Error de conexión al servidor. Por favor intenta más tarde.');
            } else {
                die('Error de conexión: ' . $e->getMessage());
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }

    public function update($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function delete($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    // Prevent cloning
    private function __clone() {}
}
