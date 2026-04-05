<?php
/**
 * RateLimiter - Protección contra ataques de fuerza bruta
 * 
 * Limita intentos de:
 * - Login
 * - Registro
 * - Password reset
 * - API calls
 * 
 * Soporta almacenamiento en:
 * - File system (default)
 * - Redis (si disponible)
 * - Session (desarrollo)
 */

class RateLimiter {
    
    private $storageDir;
    private $useRedis = false;
    private $redis = null;
    
    public function __construct($storageDir = null) {
        if ($storageDir === null) {
            $storageDir = dirname(dirname(__FILE__)) . '/logs/ratelimit/';
        }
        
        $this->storageDir = $storageDir;
        
        // Intentar usar Redis si está disponible
        if (extension_loaded('redis')) {
            try {
                $this->redis = new Redis();
                $this->redis->connect('127.0.0.1', 6379);
                $this->redis->ping();
                $this->useRedis = true;
            } catch (Exception $e) {
                // Falls back to file storage
            }
        }
        
        // Crear directorio si no existe
        if (!$this->useRedis && !is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }
    
    /**
     * Incrementar contador de intentos
     * 
     * @param string $key Identificador único (IP, usuario, etc)
     * @param int $maxAttempts Máximo de intentos permitidos
     * @param int $windowSeconds Ventana de tiempo en segundos
     * @return array ['allowed' => bool, 'attempts' => int, 'remaining' => int, 'reset_in' => int]
     */
    public function attempt($key, $maxAttempts = 5, $windowSeconds = 900) {
        $key = $this->sanitizeKey($key);
        $now = time();
        
        if ($this->useRedis) {
            return $this->attemptRedis($key, $maxAttempts, $windowSeconds, $now);
        } else {
            return $this->attemptFile($key, $maxAttempts, $windowSeconds, $now);
        }
    }
    
    /**
     * Verificar si está permitido sin incrementar
     */
    public function isAllowed($key, $maxAttempts = 5, $windowSeconds = 900) {
        $key = $this->sanitizeKey($key);
        
        if ($this->useRedis) {
            $current = $this->redis->get("ratelimit:$key:attempts") ?? 0;
            return $current < $maxAttempts;
        } else {
            $data = $this->getFileData($key);
            return ($data['attempts'] ?? 0) < $maxAttempts;
        }
    }
    
    /**
     * Resetear contador (después de login exitoso, por ejemplo)
     */
    public function reset($key) {
        $key = $this->sanitizeKey($key);
        
        if ($this->useRedis) {
            $this->redis->del("ratelimit:$key:attempts");
            $this->redis->del("ratelimit:$key:reset");
        } else {
            $file = $this->getFilePath($key);
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Rate limit usando Redis
     */
    private function attemptRedis($key, $maxAttempts, $windowSeconds, $now) {
        $attemptKey = "ratelimit:$key:attempts";
        $resetKey = "ratelimit:$key:reset";
        
        // Obtener reset time
        $resetTime = $this->redis->get($resetKey);
        
        // Si no existe o ya expiró, crear nuevo contador
        if (!$resetTime || $resetTime < $now) {
            $this->redis->setex($attemptKey, $windowSeconds, 1);
            $this->redis->setex($resetKey, $windowSeconds, $now + $windowSeconds);
            
            return [
                'allowed' => true,
                'attempts' => 1,
                'remaining' => $maxAttempts - 1,
                'reset_in' => $windowSeconds
            ];
        }
        
        // Incrementar intentos
        $attempts = $this->redis->incr($attemptKey);
        $remaining = max(0, $maxAttempts - $attempts);
        
        return [
            'allowed' => $attempts < $maxAttempts,
            'attempts' => $attempts,
            'remaining' => $remaining,
            'reset_in' => (int)($resetTime - $now)
        ];
    }
    
    /**
     * Rate limit usando archivos
     */
    private function attemptFile($key, $maxAttempts, $windowSeconds, $now) {
        $file = $this->getFilePath($key);
        $data = $this->getFileData($key);
        
        // Si no existe o expiró, crear nuevo
        if (!isset($data['reset_time']) || $data['reset_time'] < $now) {
            $data = [
                'attempts' => 1,
                'reset_time' => $now + $windowSeconds,
                'first_attempt' => $now
            ];
            $remaining = $maxAttempts - 1;
        } else {
            // Incrementar intentos
            $data['attempts']++;
            $remaining = max(0, $maxAttempts - $data['attempts']);
        }
        
        // Guardar
        $this->saveFileData($key, $data);
        
        $resetIn = $data['reset_time'] - $now;
        
        return [
            'allowed' => $data['attempts'] < $maxAttempts,
            'attempts' => $data['attempts'],
            'remaining' => $remaining,
            'reset_in' => max(0, (int)$resetIn)
        ];
    }
    
    /**
     * Sanitizar key para usarla como nombre de archivo
     */
    private function sanitizeKey($key) {
        // Convertir a hash para evitar problemas de path
        return 'rl_' . substr(hash('sha256', $key), 0, 16);
    }
    
    /**
     * Obtener ruta del archivo
     */
    private function getFilePath($key) {
        return $this->storageDir . $this->sanitizeKey($key) . '.json';
    }
    
    /**
     * Obtener datos del archivo
     */
    private function getFileData($key) {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return [];
        }
        
        $content = file_get_contents($file);
        return json_decode($content, true) ?? [];
    }
    
    /**
     * Guardar datos en archivo
     */
    private function saveFileData($key, $data) {
        $file = $this->getFilePath($key);
        file_put_contents($file, json_encode($data), LOCK_EX);
        chmod($file, 0644);
    }
    
    /**
     * Limpiar archivos expirados (ejecutar periódicamente)
     */
    public function cleanup() {
        if ($this->useRedis) {
            // Redis maneja expiración automáticamente
            return;
        }
        
        if (!is_dir($this->storageDir)) {
            return;
        }
        
        $files = glob($this->storageDir . 'rl_*.json');
        $now = time();
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            
            // Eliminar si expiró
            if (isset($data['reset_time']) && $data['reset_time'] < $now) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Obtener información para debugging
     */
    public function getStatus($key) {
        $key = $this->sanitizeKey($key);
        
        if ($this->useRedis) {
            $attempts = $this->redis->get("ratelimit:$key:attempts") ?? 0;
            $resetTime = $this->redis->get("ratelimit:$key:reset") ?? 0;
            return [
                'attempts' => $attempts,
                'reset_time' => $resetTime,
                'is_limited' => $attempts > 0
            ];
        } else {
            $data = $this->getFileData($key);
            return [
                'attempts' => $data['attempts'] ?? 0,
                'reset_time' => $data['reset_time'] ?? 0,
                'is_limited' => isset($data['attempts']) && $data['attempts'] > 0
            ];
        }
    }
}
