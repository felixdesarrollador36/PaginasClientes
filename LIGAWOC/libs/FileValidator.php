<?php
/**
 * FileValidator - Validación robusta de archivos
 * 
 * Valida:
 * - Magic bytes (firma de archivo)
 * - MIME type real (no del cliente)
 * - Tamaño máximo
 * - Extensión segura
 * Previene: RCE, file traversal, overwriting
 */

class FileValidator {
    
    // Magic bytes para cada tipo de archivo permitido
    private static $MAGIC_BYTES = [
        'jpg' => [
            [0xFF, 0xD8, 0xFF, 0xE0],  // JPEG (JFIF)
            [0xFF, 0xD8, 0xFF, 0xE1],  // JPEG (EXIF)
            [0xFF, 0xD8, 0xFF, 0xE8],  // JPEG (SPIFF)
        ],
        'png' => [
            [0x89, 0x50, 0x4E, 0x47],  // PNG
        ],
        'gif' => [
            [0x47, 0x49, 0x46, 0x38],  // GIF (87a or 89a)
        ],
        'webp' => [
            [0x52, 0x49, 0x46, 0x46],  // WebP (RIFF header)
        ],
    ];
    
    private static $MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];
    
    /**
     * Validar imagen y retornar información segura
     * 
     * @param array $file $_FILES['imagen']
     * @param int $maxSize Tamaño máximo en bytes (default: 5MB)
     * @return array ['success' => bool, 'error' => string, 'file' => array]
     */
    public static function validateImage($file, $maxSize = 5242880) {
        // Validaciones básicas
        if (!isset($file['tmp_name']) || !isset($file['error'])) {
            return ['success' => false, 'error' => 'Archivo no recibido correctamente'];
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'Archivo excede ini max_upload_size',
                UPLOAD_ERR_FORM_SIZE => 'Archivo excede max_file_size del form',
                UPLOAD_ERR_PARTIAL => 'Archivo subido parcialmente',
                UPLOAD_ERR_NO_FILE => 'No se subió archivo',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta directorio temporal',
                UPLOAD_ERR_CANT_WRITE => 'No se puede escribir archivo',
                UPLOAD_ERR_EXTENSION => 'Extensión no permitida',
            ];
            return ['success' => false, 'error' => $errors[$file['error']] ?? 'Error desconocido'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'Archivo excede tamaño máximo de ' . self::formatBytes($maxSize)];
        }
        
        if ($file['size'] == 0) {
            return ['success' => false, 'error' => 'Archivo vacío'];
        }
        
        // Obtener extensión del filename original
        $originalName = basename($file['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        // Validar extensión
        if (!isset(self::$MIME_TYPES[$ext])) {
            return ['success' => false, 'error' => 'Extensión no permitida. Use: JPG, PNG, GIF, WEBP'];
        }
        
        // Validar magic bytes (lo más importante)
        $magicResult = self::validateMagicBytes($file['tmp_name'], $ext);
        if (!$magicResult['valid']) {
            return ['success' => false, 'error' => 'Archivo no es una imagen válida (magic bytes inválidos)'];
        }
        
        // Validar MIME type real usando finfo
        $mimeType = self::getMimeType($file['tmp_name']);
        if ($mimeType !== self::$MIME_TYPES[$ext]) {
            return ['success' => false, 'error' => 'MIME type no coincide con extensión. Esperado: ' . self::$MIME_TYPES[$ext] . ', recibido: ' . $mimeType];
        }
        
        // Generar nombre seguro del archivo
        $safeFilename = self::generateSafeFilename($ext);
        
        return [
            'success' => true,
            'file' => [
                'tmp_name' => $file['tmp_name'],
                'original_name' => $originalName,
                'safe_name' => $safeFilename,
                'extension' => $ext,
                'size' => $file['size'],
                'mime_type' => $mimeType,
                'magic_verified' => true,
            ]
        ];
    }
    
    /**
     * Validar magic bytes del archivo
     */
    private static function validateMagicBytes($filePath, $ext) {
        if (!isset(self::$MAGIC_BYTES[$ext])) {
            return ['valid' => false];
        }
        
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return ['valid' => false];
        }
        
        // Leer primeros 4 bytes
        $magic = fread($handle, 4);
        fclose($handle);
        
        $bytes = array_values(unpack('C*', $magic));
        
        // Comparar con magic bytes conocidos
        foreach (self::$MAGIC_BYTES[$ext] as $expected) {
            // Para WebP, solo comparar primeros 4 bytes de RIFF
            if ($ext === 'webp') {
                if ($bytes[0] === 0x52 && $bytes[1] === 0x49 && 
                    $bytes[2] === 0x46 && $bytes[3] === 0x46) {
                    // Validación adicional para WebP: verificar "WEBP" en 8-11
                    $handle = fopen($filePath, 'rb');
                    fseek($handle, 8);
                    $webpSignature = fread($handle, 4);
                    fclose($handle);
                    if ($webpSignature === 'WEBP') {
                        return ['valid' => true];
                    }
                }
            } else {
                if ($bytes === $expected) {
                    return ['valid' => true];
                }
            }
        }
        
        return ['valid' => false];
    }
    
    /**
     * Obtener MIME type real usando finfo
     */
    private static function getMimeType($filePath) {
        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            return $mime;
        }
        
        // Fallback a mime_content_type (deprecado pero mejor que nada)
        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        }
        
        return 'application/octet-stream';
    }
    
    /**
     * Generar nombre de archivo seguro
     * Previene directory traversal, overwriting, ejecución PHP
     */
    public static function generateSafeFilename($ext) {
        // Nombre aleatorio seguro + timestamp + extensión
        $timestamp = date('YmdHis');
        $random = bin2hex(random_bytes(8));
        return $timestamp . '_' . $random . '.' . strtolower($ext);
    }
    
    /**
     * Mover archivo a ubicación segura
     * Almacenar FUERA del web root es lo ideal
     */
    public static function moveToPrivateStorage($tmpPath, $safeFilename, $storageDir) {
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }
        
        $destination = $storageDir . '/' . $safeFilename;
        
        // Validaciones antes de mover
        if (!is_uploaded_file($tmpPath)) {
            return ['success' => false, 'error' => 'Archivo no viene de upload'];
        }
        
        if (file_exists($destination)) {
            return ['success' => false, 'error' => 'Archivo ya existe (nombre colisión)'];
        }
        
        if (!move_uploaded_file($tmpPath, $destination)) {
            return ['success' => false, 'error' => 'No se pudo mover archivo a almacenamiento'];
        }
        
        // Proteger permisos  
        chmod($destination, 0644);
        
        return ['success' => true, 'path' => $destination];
    }
    
    /**
     * Formatear bytes a unidad legible
     */
    private static function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Validar que archivo no sea ejecutable
     * Previene .php, .php7, .phtml, etc
     */
    public static function isExecutable($filename) {
        $executableExtensions = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'inc', 'hphp', 'ctp', 'shtml'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, $executableExtensions);
    }
}
