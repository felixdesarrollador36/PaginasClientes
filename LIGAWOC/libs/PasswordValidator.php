<?php
/**
 * PasswordValidator - Validación robusta de contraseñas
 * 
 * Valida:
 * - Longitud mínima (12 caracteres)
 * - Complejidad (mayúscula, minúscula, número, especial)
 * - Contra diccionarios comunes
 * - Contra informaciones del usuario (nombre, email, etc)
 */

class PasswordValidator {
    
    // Palabras comunes en contraseñas débiles
    private static $COMMON_PASSWORDS = [
        'password', 'password123', '123456', '12345678', 'qwerty', 'abc123',
        'letmein', 'welcome', 'admin', 'admin123', 'pass', 'pass123',
        'password1', 'password12', 'password123', '111111', '1234567',
        'dragon', 'master', 'sunshine', 'mustang', 'shadow', 'ashley',
        'bailey', 'passw0rd', 'shadow123', 'sunshine123', 'ligawoc',
        'dominicana', 'mobile', 'legends', 'gamer', 'gaming', 'eSports',
        'tournament', 'tournament123', 'admin1234', 'root', 'root123'
    ];
    
    /**
     * Validar contraseña completa
     * 
     * @param string $password Contraseña a validar
     * @param string $username Username del usuario (para evitar usos en password)
     * @param string $email Email del usuario
     * @return array ['valid' => bool, 'strength' => string, 'errors' => array]
     */
    public static function validate($password, $username = '', $email = '') {
        $errors = [];
        $strength = 'weak';
        
        // 1. Validar longitud
        if (strlen($password) < 12) {
            $errors[] = 'Mínimo 12 caracteres (actual: ' . strlen($password) . ')';
        }
        
        if (strlen($password) > 128) {
            $errors[] = 'Máximo 128 caracteres';
        }
        
        // 2. Validar complejidad
        $hasUpper = preg_match('/[A-Z]/', $password);
        $hasLower = preg_match('/[a-z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);
        $hasSpecial = preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password);
        
        $complexity = 0;
        if (!$hasUpper) $errors[] = 'Debe contener al menos una MAYÚSCULA (A-Z)';
        else $complexity++;
        
        if (!$hasLower) $errors[] = 'Debe contener al menos una minúscula (a-z)';
        else $complexity++;
        
        if (!$hasNumber) $errors[] = 'Debe contener al menos un número (0-9)';
        else $complexity++;
        
        if (!$hasSpecial) $errors[] = 'Debe contener al menos un carácter especial (!@#$%^&*)';
        else $complexity++;
        
        // 3. Validar contra palabras comunes
        $passwordLower = strtolower($password);
        foreach (self::$COMMON_PASSWORDS as $common) {
            if (strpos($passwordLower, $common) !== false) {
                $errors[] = 'Contiene una palabra común. Elige una contraseña más única.';
                break;
            }
        }
        
        // 4. Validar que no contenga información del usuario
        if (!empty($username) && strlen($username) > 3) {
            if (strpos($passwordLower, strtolower($username)) !== false) {
                $errors[] = 'La contraseña no puede contener el nombre de usuario';
            }
        }
        
        if (!empty($email)) {
            $emailParts = explode('@', strtolower($email));
            foreach ($emailParts as $part) {
                if (strlen($part) > 3 && strpos($passwordLower, $part) !== false) {
                    $errors[] = 'La contraseña no puede contener partes de tu email';
                }
            }
        }
        
        // 5. Detectar patrones débiles
        if (self::hasRepeatingChars($password)) {
            $errors[] = 'Evita caracteres repetidos (ejemplo: "aaa", "111")';
        }
        
        if (self::hasSequentialChars($password)) {
            $errors[] = 'Evita secuencias (ejemplo: "abc", "123")';
        }
        
        // 6. Calcular fortaleza
        if (empty($errors)) {
            if ($complexity === 4 && strlen($password) >= 16) {
                $strength = 'very-strong';
            } elseif ($complexity === 4 && strlen($password) >= 12) {
                $strength = 'strong';
            } elseif ($complexity >= 3) {
                $strength = 'medium';
            }
        } elseif (count($errors) === 1) {
            $strength = 'medium';
        }
        
        return [
            'valid' => empty($errors),
            'strength' => $strength,
            'errors' => $errors,
            'complexity' => $complexity
        ];
    }
    
    /**
     * Detectar caracteres repetidos
     */
    private static function hasRepeatingChars($password) {
        return (bool)preg_match('/(.)\1{2,}/', $password);
    }
    
    /**
     * Detectar secuencias
     */
    private static function hasSequentialChars($password) {
        // Detectar abc, 123, etc
        $length = strlen($password);
        for ($i = 0; $i < $length - 2; $i++) {
            $c1 = ord($password[$i]);
            $c2 = ord($password[$i+1]);
            $c3 = ord($password[$i+2]);
            
            // Secuencia ascendente
            if ($c2 === $c1 + 1 && $c3 === $c2 + 1) {
                return true;
            }
            
            // Secuencia descendente
            if ($c2 === $c1 - 1 && $c3 === $c2 - 1) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Generar contraseña segura (para reset)
     */
    public static function generate($length = 16) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        // Asegurar que tiene de todo
        $requirements = [
            'upper' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'lower' => 'abcdefghijklmnopqrstuvwxyz',
            'digit' => '0123456789',
            'special' => '!@#$%^&*'
        ];
        
        foreach ($requirements as $requirement) {
            if (strpos($password, $requirement[random_int(0, strlen($requirement) - 1)]) === false) {
                $randomPos = random_int(0, strlen($password) - 1);
                $password[$randomPos] = $requirement[random_int(0, strlen($requirement) - 1)];
            }
        }
        
        // Shuffle
        $password = str_shuffle($password);
        
        return $password;
    }
    
    /**
     * Obtener sugerencias de mejora
     */
    public static function getSuggestions() {
        return [
            '✓ Usa al menos 12 caracteres',
            '✓ Mezcla mayúsculas, minúsculas, números y símbolos',
            '✓ Evita palabras del diccionario',
            '✓ No uses información personal (nombre, email, fechas)',
            '✓ Evita repeticiones (aaa, 111)',
            '✓ Evita secuencias (abc, 123)',
            '✓ Ejemplo fuerte: Mx7@2024!GaMe#24'
        ];
    }
    
    /**
     * Calcular entropía de la contraseña (opcional)
     */
    public static function getEntropy($password) {
        $characterSpace = 0;
        
        if (preg_match('/[a-z]/', $password)) $characterSpace += 26;
        if (preg_match('/[A-Z]/', $password)) $characterSpace += 26;
        if (preg_match('/[0-9]/', $password)) $characterSpace += 10;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $characterSpace += 32;
        
        $entropy = strlen($password) * log($characterSpace, 2);
        
        // Clasificar
        if ($entropy < 50) $level = 'Muy débil';
        elseif ($entropy < 80) $level = 'Débil';
        elseif ($entropy < 100) $level = 'Moderada';
        elseif ($entropy < 130) $level = 'Fuerte';
        else $level = 'Muy fuerte';
        
        return ['entropy' => round($entropy, 2), 'level' => $level];
    }
}
