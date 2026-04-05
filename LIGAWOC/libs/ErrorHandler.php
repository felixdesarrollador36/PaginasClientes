<?php
/**
 * ErrorHandler - Manejo centralizado de errores
 * 
 * Proporciona respuestas consistentes en:
 * - Errores de acceso (401, 403)
 * - Recursos no encontrados (404)
 * - Errores de validación (422)
 * - Errores del servidor (500)
 */

class ErrorHandler {
    
    private static $isJson = false;
    
    /**
     * Detectar si se debe responder con JSON
     */
    public static function init() {
        // Detectar si es AJAX o Content-Type es JSON
        self::$isJson = (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) || (
            isset($_SERVER['CONTENT_TYPE']) && 
            strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
        );
        
        // Set error handlers
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
    }
    
    /**
     * Manejo de errores PHP
     */
    public static function handleError($errno, $errstr, $errfile, $errline) {
        $isProduction = env('APP_ENV') === 'production';
        
        // No mostrar notices en producción
        if ($isProduction && $errno === E_NOTICE) {
            return true;
        }
        
        error_log("[$errno] $errstr in $errfile:$errline");
        
        if (self::$isJson) {
            self::jsonError($errstr, 500);
        } else {
            if (!$isProduction) {
                echo "<pre style='background:#f8f9fa; padding:20px; color:#721c24; border: 1px solid #f5c6cb; border-radius:4px;'>";
                echo "Error [$errno]: $errstr\n";
                echo "File: $errfile\n";
                echo "Line: $errline\n";
                echo "</pre>";
            }
        }
        
        return true;
    }
    
    /**
     * Manejo de excepciones no capturadas
     */
    public static function handleException($exception) {
        error_log($exception->getMessage());
        
        if (self::$isJson) {
            self::jsonError($exception->getMessage(), 500);
        } else {
            self::htmlError('Error', $exception->getMessage(), 500);
        }
    }
    
    /**
     * Error de acceso denegado (403)
     */
    public static function forbidden($message = 'Acceso denegado') {
        http_response_code(403);
        
        if (self::$isJson) {
            self::jsonError($message, 403);
        } else {
            self::htmlError('Acceso Denegado', $message, 403);
        }
    }
    
    /**
     * Error de no autenticado (401)
     */
    public static function unauthorized($message = 'Debes iniciar sesión') {
        http_response_code(401);
        
        if (self::$isJson) {
            self::jsonError($message, 401);
        } else {
            setFlash('warning', $message);
            redirect('login');
        }
    }
    
    /**
     * Error de no encontrado (404)
     */
    public static function notFound($message = 'Recurso no encontrado') {
        http_response_code(404);
        
        if (self::$isJson) {
            self::jsonError($message, 404);
        } else {
            self::htmlError('No Encontrado', $message, 404);
        }
    }
    
    /**
     * Error de validación (422)
     */
    public static function validationError($errors = []) {
        http_response_code(422);
        
        if (self::$isJson) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Errores de validación',
                'errors' => $errors,
                'code' => 422
            ]);
            exit;
        } else {
            setFlash('error', implode('<br>', (array)$errors));
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }
    }
    
    /**
     * Error genérico
     */
    public static function error($message = 'Error', $code = 500) {
        http_response_code($code);
        
        if (self::$isJson) {
            self::jsonError($message, $code);
        } else {
            self::htmlError('Error', $message, $code);
        }
    }
    
    /**
     * Respuesta JSON de error
     */
    private static function jsonError($message, $code) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code
        ]);
        exit;
    }
    
    /**
     * Respuesta HTML de error
     */
    private static function htmlError($title, $message, $code) {
        if (function_exists('isLoggedIn') && isLoggedIn()) {
            // Usuario logueado - mostrar página con navegación
            ?>
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title><?= $code ?> - <?= htmlspecialchars($title) ?></title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; }
                    .error-container { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
                    .error-card { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 40px; max-width: 600px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); }
                    .error-code { font-size: 64px; font-weight: 700; color: #f43f5e; margin-bottom: 10px; }
                    .error-title { font-size: 28px; font-weight: 600; margin-bottom: 10px; }
                    .error-message { color: #cbd5e1; font-size: 16px; margin-bottom: 30px; }
                    .btn { display: inline-block; padding: 10px 20px; background: #7c3aed; color: white; text-decoration: none; border-radius: 6px; margin-right: 10px; border: none; cursor: pointer; }
                    .btn:hover { background: #6d28d9; }
                    .btn-secondary { background: #475569; }
                    .btn-secondary:hover { background: #334155; }
                </style>
            </head>
            <body>
                <div class="error-container">
                    <div class="error-card">
                        <div class="error-code"><?= $code ?></div>
                        <div class="error-title"><?= htmlspecialchars($title) ?></div>
                        <div class="error-message"><?= htmlspecialchars($message) ?></div>
                        <div>
                            <a href="<?= url('dashboard') ?>" class="btn">Ir al Dashboard</a>
                            <a href="javascript:history.back()" class="btn btn-secondary">Volver</a>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            <?php
        } else {
            // No logueado - simple
            ?>
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <title><?= $code ?> - Error</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
                    h1 { color: #d32f2f; }
                </style>
            </head>
            <body>
                <h1><?= $code ?> - <?= htmlspecialchars($title) ?></h1>
                <p><?= htmlspecialchars($message) ?></p>
                <a href="<?= url('') ?>">Ir a inicio</a>
            </body>
            </html>
            <?php
        }
        exit;
    }
    
    /**
     * Success response (para casos de API)
     */
    public static function jsonSuccess($data = [], $message = 'Éxito', $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'code' => $code
        ]);
        exit;
    }
}

// Inicializar handlers
ErrorHandler::init();
