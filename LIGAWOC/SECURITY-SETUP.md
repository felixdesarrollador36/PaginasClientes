# 🔒 CONFIGURACIÓN DE SEGURIDAD - CRÍTICA

## ⚠️ PASO 1: CREAR ARCHIVO .env

**IMPORTANTE:** Este archivo contiene credenciales sensibles y NUNCA debe compartirse.

### En tu servidor local o hosting:

```bash
cp .env.example .env
```

### Edita `.env` y reemplaza con tus credenciales reales:

```
APP_ENV=production
APP_DEBUG=false

# Base de datos
DB_HOST=localhost
DB_NAME=ligaojmj_WOC
DB_USER=ligaojmj_Woc
DB_PASS=TU_CONTRASEÑA_BD_AQUI

# Correo SMTP
SMTP_HOST=mail.ligawocdominicana.com
SMTP_USER=info@ligawocdominicana.com
SMTP_PASS=TU_PASSWORD_SMTP_AQUI
SMTP_PORT=465

# PayPal
PAYPAL_MODE=live
PAYPAL_CLIENT_ID=ARg8eHdzqqf-38NV-... (tu ID real)
PAYPAL_CLIENT_SECRET=EAXrwVSZ0qlLRQYe3... (tu SECRET real)
PAYPAL_WEBHOOK_ID=TU_WEBHOOK_ID_AQUI

# Seguridad
SESSION_TIMEOUT=3600
SECURE_COOKIES=true
SAMESITE_COOKIES=Strict
```

---

## ✅ PASO 2: VERIFICAR QUE .gitignore PROTEGE .env

El archivo `.gitignore` ya incluye:
```
.env
.env.local
.env.*.local
```

Esto previene que `.env` se suba a Git accidentalmente.

---

## 🛡️ CAMBIOS DE SEGURIDAD IMPLEMENTADOS

### 1. **Variables de Entorno** ✓
- ✅ Credenciales NO hardcodeadas
- ✅ Sistema `.env` implementado
- ✅ Función `env()` para acceso seguro

### 2. **Error Reporting** ✓
- ✅ `display_errors = 0` en producción
- ✅ Logs privados en `/logs/error.log`
- ✅ `.htaccess` previene acceso público a error_log

### 3. **Session Security** ✓
- ✅ Timeout: 1 hora (antes: 30 días)
- ✅ SameSite: Strict (antes: Lax)
- ✅ HTTP-Only: enabled
- ✅ Secure: 1 (HTTPS only)

### 4. **Archivos Sensibles Bloqueados** ✓
Con `.htaccess`:
- ✅ Bloquea `/config/`
- ✅ Bloquea `.env`
- ✅ Bloquea `.git/`
- ✅ Bloquea `error_log`
- ✅ Bloquea `/logs/`
- ✅ Previene ejecución PHP en `/uploads/`

### 5. **Headers de Seguridad** ✓
- X-Content-Type-Options: nosniff
- X-Frame-Options: SAMEORIGIN
- X-XSS-Protection: 1; mode=block
- Referrer-Policy: strict-origin-when-cross-origin
- Permissions-Policy: geolocation, microphone, camera bloqueados

---

## 📋 PRÓXIMOS PASOS CRÍTICOS

### Usar en index.php o en un bootstrap file:

```php
// En index.php, lo primero antes de incluir configs:
require_once __DIR__ . '/config/env-loader.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/paypal.php';
```

### En producción (cPanel/Hosting):

Muchos hosts cPanel permiten variables de entorno via:
1. **cPanel > Environment Variables**
2. **O agregar a `.htaccess`:**
```apache
SetEnv DB_HOST localhost
SetEnv DB_NAME ligaojmj_WOC
SetEnv DB_USER ligaojmj_Woc
SetEnv DB_PASS tu_password
```

3. **O en `/public_html/.htenv`** (algunos hosts)

---

## 🔐 CAMBIAR CREDENCIALES EXPUESTAS

Dado que estas credenciales estuvieron expuestas:

1. **SMTP Password** - Cambiar en panel cPanel/hosting
2. **DB Password** - Cambiar en hosting/phpmyadmin
3. **PayPal Keys** - Invalidar y generar nuevas en PayPal Developer
4. **FTP Password** - Cambiar en hosting

### Comando para forzar nueva sesión de login:

```php
// En un archivo temporal en /admin/:
session_start();
session_destroy();
// Luego acceder a login para generar nueva sesión
```

---

## ⚠️ VERIFICACIÓN DE SEGURIDAD

Después de implementar, verifica:

1. **¿.env existe y tiene credenciales?**
   ```bash
   cat .env  # En Linux/Mac
   type .env # En Windows
   ```

2. **¿.env está en .gitignore?**
   ```bash
   git check-ignore .env  # Debe retornar .env
   ```

3. **¿Puedo acceder a error_log?**
   - Abre: https://ligawocdominicana.com/error_log
   - Debe dar: **403 Forbidden** (error_log bloqueado)

4. **¿Puedo acceder a /config/?**
   - Abre: https://ligawocdominicana.com/config/
   - Debe dar: **403 Forbidden** (directorio bloqueado)

5. **¿Las credenciales cargan correctamente?**
   ```php
   // En un archivo de prueba /test.php (temporal):
   require_once 'config/env-loader.php';
   var_dump([
       'DB_HOST' => env('DB_HOST'),
       'SMTP_USER' => env('SMTP_USER'),
       'PAYPAL_MODE' => env('PAYPAL_MODE')
   ]);
   // Debe mostrar los valores correctamente
   ```

---

## 📝 SIGUIENTES VULNERABILIDADES A CORREGIR

- [ ] **CSRF incompleto** - Agregar tokens en GET requests admin
- [ ] **Upload sin validación** - Implementar validación de magic bytes
- [ ] **Rate limiting** - Añadir límite de intentos de login
- [ ] **Autorización/Auditoría** - Registrar cambios admin

Próxima fase cuando termines de configurar esto.
