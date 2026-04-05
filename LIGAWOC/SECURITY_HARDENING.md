# 🔐 Security Hardening - Liga WOC

## Resumen de cambios aplicados (19 de Marzo de 2026)

Se han implementado las siguientes medidas de seguridad:

### ✅ APLICADAS INMEDIATAMENTE

1. **Session Fixation Prevention** (`config/app.php` y `controllers/AuthController.php`)
   - Se regenera el ID de sesión al iniciar la aplicación
   - Se regenera nuevamente tras un login exitoso
   - Previene ataques de fixación de sesión

2. **DB Error Handling** (`config/database.php`)
   - En producción: oculta detalles técnicos de BD
   - Los errores se loguean internamente pero no se exponen al usuario
   - Requiere: `APP_ENV=production` en `.env`

3. **Enhanced CSP Headers** (`.htaccess`)
   - Añadidos controles adicionales: `base-uri 'self'`, `form-action 'self'`
   - Previene ataques de inyección de formularios
   - Bloquea intentos de manipulación de base URL

4. **PHP Access Control** (`.htaccess`)
   - Redundancia: bloquea acceso directo a `.php` en directorios sensibles
   - Refuerza el bloqueo existente en `/config`, `/includes`, `/libs`, `/database`

---

## ⚠️ REQUISITOS DE CONFIGURACIÓN EN PRODUCCIÓN

Antes de exponer la web en producción, ejecuta estas acciones:

### 1. Configurar `.env` para Producción

```bash
# .env
APP_ENV=production
SECURE_COOKIES=true
SAMESITE_COOKIES=Strict
SESSION_TIMEOUT=3600
DB_HOST=tu-servidor-mysql
DB_USER=usuario_seguro
DB_PASS=contraseña_fuerte
PAYPAL_MODE=live
PAYPAL_CLIENT_ID=tu_client_id_paypal_live
PAYPAL_CLIENT_SECRET=tu_client_secret_paypal_live
HTTPS=on
```

### 2. Habilitar HTTPS

- Obtén un certificado SSL/TLS (ej: Let's Encrypt)
- Configura Apache/Nginx para servir HTTPS
- Redirige todo HTTP → HTTPS

El código ya detecta HTTPS y aplica headers `Strict-Transport-Security` automáticamente.

### 3. Validar Headers de Seguridad están llegando al navegador

Después de configurar HTTPS, verifica que los headers se envíen:

```bash
curl -I https://tudominio.com/
```

Deberías ver:
```
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Content-Security-Policy: ...
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
Referrer-Policy: strict-origin-when-cross-origin
```

### 4. Permisos de Directorios

```bash
# Config y libs deben ser leíbles pero NO ejecutables como directorios web
chmod 750 config/ includes/ libs/ database/

# Uploads deben ser escribibles pero NO ejecutables
chmod 755 assets/uploads/ assets/uploads/avatars/ assets/uploads/matches/

# .env debe ser privado
chmod 600 .env

# Logs privados
chmod 700 logs/ logs/ratelimit/
```

### 5. Ocultar Información del Servidor

En `.htaccess` o configuración Apache:

```apache
# Remover cabecera de versión de servidor
Header always unset X-Powered-By
Header unset X-Powered-By
```

### 6. Deshabilitar Directory Listing

Ya está configurado en `.htaccess`:
```apache
Options -Indexes
```

Verifica en producción que no se muestren listados de directorios.

---

## 📋 PRÓXIMAS MEJORAS (Priority List)

### Alto (hazlo antes de IR A PRODUCCIÓN)

- [ ] **Eliminar `unsafe-inline` de CSP**
  - Refactoriza scripts inline en `onclick` handlers
  - Usa archivos JavaScript externos o nonces
  - Mejora significativa de seguridad XSS

- [ ] **Implementar Rate Limiting más agresivo**
  - Login: máximo 3 intentos / 10 minutos
  - Registro: máximo 1 por email / 24 horas
  - API: máximo 100 requests / minuto por IP

- [ ] **Two-Factor Authentication (2FA)**
  - Código TOTP generado en app authenticator
  - O email OTP para cada login desde nuevo dispositivo

- [ ] **Audit Logging**
  - Log todos los accesos y cambios en BD
  - Tabla `audit_log` con: user_id, action, resource, timestamp, IP
  - Revisar logs diariamente en producción

### Medio (hazloantes de crecer en usuarios)

- [ ] **Web Application Firewall (WAF)**
  - CloudFlare, Sucuri, o ModSecurity en Apache
  - Protege contra OWASP Top 10

- [ ] **SQL Injection Testing**
  - Aunque uses PDO prepared statements, prueba con herramientas como SQLMap
  - Valida que no hay inyecciones posibles

- [ ] **Password Policy**
  - Refuerza requisitos: mín 12 caracteres, mayúsculas, números, símbolos
  - Revisa `libs/PasswordValidator.php`

- [ ] **API Rate Limiting por usuario**
  - No solo por IP
  - Asocia límites a `user_id`

### Bajo (nice to have)

- [ ] Implementar Content Security Policy con nonces para scripts inline
- [ ] Subdominio aislado para uploads: `uploads.tudominio.com`
- [ ] Monitoreo de seguridad: Sentry, New Relic
- [ ] Penetration Testing profesional antes de producción

---

## 🔍 CHECKLIST DE SEGURIDAD PRE-PRODUCCIÓN

```
AUTENTICACIÓN Y SESIONES
☐ Las contraseñas usan bcrypt (password_hash) ✓ Done
☐ Sessions tienen HttpOnly + SameSite=Strict ✓ Done
☐ Session ID regenerado tras login ✓ Done (19/03)
☐ Timeout de inactividad implementado ✓ Done
☐ Rate limiting en login y registro ✓ Done

PROTECCIÓN CONTRA ATAQUES
☐ CSRF tokens en todos los formularios ✓ Done
☐ Prepared statements en todas las queries ✓ Done
☐ Validación de magic bytes en uploads ✓ Done
☐ Content Security Policy configurada ✓ Done
☐ X-Frame-Options previene clickjacking ✓ Done
☐ X-Content-Type-Options: nosniff ✓ Done

ERRORES Y LOGGING
☐ Errores ocultos en producción ✓ Done (19/03)
☐ Logs de eventos sensibles registrados ✓ Done
☐ Directorio /logs protegido ☐ (requiere chmod 700)

SERVIDOR Y RED
☐ HTTPS obligatorio ☐ (requiere configuración Apache)
☐ HSTS habilitado en .htaccess ✓ Done
☐ Directory listing deshabilitado ✓ Done
☐ .env privado (no en carpeta web) ☐ (requiere chmod 600)

ARQUIVOS DE CONFIGURACIÓN
☐ .env no versionado en Git ✓ Asume .gitignore
☐ Credenciales BD en variables de entorno ✓ Done
☐ PayPal keys en .env, no hardcoded ✓ Done
```

---

## 📞 En caso de incidente de seguridad

1. **Revoca tokens de sesión**
   - Ejecuta: `DELETE FROM sessions WHERE user_id = X;`

2. **Force password reset**
   - Envía email al usuario con link temporal

3. **Revisa logs de ataque**
   - Busca en `/logs/` patrones sospechosos
   - Busca en `audit_log` cambios no autorizados

4. **Cambia credenciales**
   - BD, PayPal, SMTP, cualquiera que pudiera haber sido comprometida

---

## 🎓 Referencias

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [Content Security Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)
- [Session Security](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)

---

Última revisión: 19/03/2026  
Prioridad: 🔴 CRÍTICA - Aplica antes de producción
