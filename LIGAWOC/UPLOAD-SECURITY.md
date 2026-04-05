# 📁 VALIDACIÓN SEGURA DE UPLOADS

## Cambios Implementados

### 1. **Clase FileValidator** ✅
Se ha creado `/libs/FileValidator.php` que implementa:
- **Validación de magic bytes** - Verifica la firma real del archivo
- **MIME type real** - Usa `finfo_file()` en lugar de solo `$_FILES['type']`
- **Nombres seguros de archivo** - Genera nombres aleatorios para prevenir colisiones
- **Protección contra directory traversal** - Valida que no contenga `../`
- **Prevención de RCE** - Bloquea extensiones ejecutables

### 2. **Actualización de Endpoints**
Se han actualizado estos archivos para usar `FileValidator`:
- ✅ **index.php** (L156-185) - Upload de cover de usuario
- ✅ **pages/designer.php** (L59-88) - Upload de items de tienda

### 3. **Protección en Directorios de Uploads**
Se crearon archivos `.htaccess` para prevenir ejecución PHP:
- ✅ `/assets/uploads/.htaccess`
- ✅ `/assets/shop/.htaccess`

Estos bloquean:
- Extensiones PHP (.php, .php5, .phtml, .phar, etc)
- Ejecución de scripts vía CGI
- Permiten solo tipos de archivo seguros

---

## Cómo Usar FileValidator

### En un endpoint de upload:

```php
<?php
require_once __DIR__ . '/libs/FileValidator.php';

if (isset($_FILES['imagen'])) {
    // Validar imagen
    $validation = FileValidator::validateImage(
        $_FILES['imagen'],
        5 * 1024 * 1024  // Max 5MB (opcional)
    );
    
    if ($validation['success']) {
        $fileInfo = $validation['file'];
        // $fileInfo contiene:
        // - tmp_name: ruta temporal
        // - safe_name: nombre seguro del archivo
        // - extension: extensión validada
        // - size: tamaño
        // - mime_type: MIME type real
        // - magic_verified: true (magic bytes validados)
        
        // Mover a almacenamiento
        $uploadDir = __DIR__ . '/private_uploads/';
        $moveResult = FileValidator::moveToPrivateStorage(
            $fileInfo['tmp_name'],
            $fileInfo['safe_name'],
            $uploadDir
        );
        
        if ($moveResult['success']) {
            // Guardar en BD el nombre seguro
            $db->insert("INSERT INTO files ...", [$fileInfo['safe_name']]);
        } else {
            echo "Error: " . $moveResult['error'];
        }
    } else {
        echo "Error de validación: " . $validation['error'];
    }
}
?>
```

---

## Validaciones Que Realiza

### 1. **Magic Bytes**
Para cada tipo de archivo, valida su firma binaria:

```
JPG: FF D8 FF E0/E1/E8
PNG: 89 50 4E 47
GIF: 47 49 46 38
WebP: 52 49 46 46 ... 57 45 42 50
```

Un atacante NO puede falsificar estos bytes sin que la imagen sea inválida.

### 2. **MIME Type Real**
Usa `finfo_file()` que verifica el contenido actual, no la extensión.

### 3. **Nombre Seguro**
Genera: `YYYYMMDDHHmmss_XXXXXXXX.ext`
- No usa información del usuario
- Imposible de adivinar
- Previene overwriting de archivos

### 4. **Protección .htaccess**
Incluso si alguien logra subir PHP, no se ejecutará:
```apache
<FilesMatch "\.php$">
    Require all denied
</FilesMatch>
```

---

## Arquitecturas Recomendadas

### Opción 1: Almacenamiento Privado (RECOMENDADO)
```
/private_uploads/  ← FUERA del webroot
    /avatars/
    /covers/
    /shop/
```

Acceso via PHP:
```php
// Servir archivo privado
header('Content-Type: image/png');
readfile('/var/private_uploads/avatar.png');
```

### Opción 2: Almacenamiento Público (ACTUAL)
```
/public_html/assets/uploads/
    /avatars/
    /covers/
    /shop/
```

Protegido por:
1. Extensiones generadas aleatoriamente
2. .htaccess bloqueando PHP
3. Sin acceso directo

---

## Qué NO Hacer

❌ **NUNCA** validar solo `$_FILES['type']`
```php
// MAL ❌
if ($file['type'] === 'image/jpeg') { ... }
```

❌ **NUNCA** usar nombre original del usuario
```php
// MAL ❌
$filename = $_FILES['image']['name'];
```

❌ **NUNCA** almacenar en webroot sin .htaccess
```php
// MAL ❌
$uploadDir = '/var/www/html/uploads/';
// Sin .htaccess ~ RCE
```

❌ **NUNCA** confiar en extensión
```php
// MAL ❌
$ext = pathinfo($file, PATHINFO_EXTENSION);
if ($ext === 'jpg') { ... }  // Fácil falsear
```

---

## Testing de Seguridad

### Crear archivo .php.jpg para probar:
```bash
# Crear JPEG válido + código PHP
cp image.jpg malicious.php.jpg

# El servidor debería servir como imagen, no ejecutar PHP
curl https://ligawocdominicana.com/assets/uploads/malicious.php.jpg
```

### Resultado esperado:
- ✅ Descarga como imagen
- ✅ No ejecuta PHP
- ❌ (Lo que se debe evitar) Ejecutar PHP

---

## Monitoreo

Revisar logs de intentos fallidos:
```php
// En FileValidator
error_log('Invalid file upload attempt: ' . $validation['error']);
```

Monitorear patrones de ataque:
- Intentos con .php.jpg, .php.png
- Múltiples fallos de magic bytes
- Cambios detectados en upload_tmp_dir

---

## Integración Futura

Si planeas integrar esto en más endpoints:

```php
// 1. Crear un helper
// functions/upload.php
function uploadAndValidateImage($fileKey, $uploadDir, $maxSize = 5MB) {
    // ... wrapper reutilizable
}

// 2. Usar en múltiples lugares
$result = uploadAndValidateImage('avatar', '/assets/avatars/');
```
