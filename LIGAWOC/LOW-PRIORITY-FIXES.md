# 🔒 Seguridad - Problemas Bajos - Implementación Completa

## Resumen
Se completaron los 5 problemas de baja prioridad solicitados.

---

## 1. CSRF faltante en acciones de admin/shop ✅

### Problema
En el listado general de items de tienda, el botón de aprobar no incluía token CSRF.

### Solución
- Archivo actualizado: `admin/shop.php`
- Se añadió `csrf_token` al enlace de aprobación en la tabla principal.

---

## 2. CSRF faltante y orden de inicialización en admin/teams ✅

### Problema
- Acciones `verify`, `activate`, `deactivate` se ejecutaban por GET sin validación CSRF.
- `$db` se usaba antes de inicializarse en el bloque de acciones.

### Solución
- Archivo actualizado: `admin/teams.php`
- Se inicializa `$db` antes de procesar acciones.
- Se añadió `verifyCsrfRequest()` en acciones.
- Se añadió `csrf_token` a todos los enlaces de acción.

---

## 3. Validación CSRF incorrecta en delete de admin/news ✅

### Problema
El borrado de noticias se invocaba por GET, pero se validaba con `verifyCsrf()` (solo POST).

### Solución
- Archivo actualizado: `admin/news.php`
- Se reemplazó por `verifyCsrfRequest()` para aceptar token GET/POST.

---

## 4. API de gestión de equipos sin CSRF en peticiones AJAX ✅

### Problema
Los endpoints de API (`approve-request`, `reject-request`, `kick-member`, `change-role`) no exigían token CSRF.

### Solución
- Archivo actualizado: `controllers/TeamController.php`
- Se añadió `verifyCsrfRequest()` en los 4 métodos.
- Archivo actualizado: `pages/team-manage.php`
- `fetch()` ahora envía `X-CSRF-Token` en headers en todas las acciones.
- Archivo actualizado: `config/app.php`
- `verifyCsrfRequest()` ahora también lee `HTTP_X_CSRF_TOKEN`.

---

## 5. Acciones equip/unequip en inventario/perfil sin protección completa ✅

### Problema
Las acciones `equip/unequip` (inventario) y `equip-marco/equip-portada` (perfil) se ejecutaban por URL sin validación CSRF y sin chequeo explícito de acceso al recurso.

### Solución
- Archivo actualizado: `index.php`
  - Se añadió carga de `ResourceValidator`.
  - Se exige `verifyCsrfRequest()` en equip/unequip y equip de perfil.
  - Se aplica validación de acceso con `UserCanAccessInventory(...)`.
  - Se mejoró feedback de errores con `setFlash` si falla equip/unequip.
- Archivo actualizado: `pages/inventory.php`
  - JS añade `csrf_token` a URLs de equip/unequip.
- Archivo actualizado: `pages/profile.php`
  - JS añade `csrf_token` a URLs de equipar marco/portada.

---

## Hardening adicional incluido

### Reenvío de código de verificación de email
- Archivo actualizado: `controllers/AuthController.php`
- `resendEmailVerificationCode()` ahora exige `verifyCsrf()`.
- Archivo actualizado: `pages/profile.php`
- El botón de reenvío cambió de navegación GET a envío POST con token CSRF.

---

## Archivos modificados en esta fase

1. `admin/shop.php`
2. `admin/teams.php`
3. `admin/news.php`
4. `controllers/TeamController.php`
5. `config/app.php`
6. `index.php`
7. `pages/team-manage.php`
8. `pages/inventory.php`
9. `pages/profile.php`
10. `controllers/AuthController.php`

---

## Estado final

- ✅ Críticos: 5/5
- ✅ Altos: 5/5
- ✅ Medios: 5/5
- ✅ Bajos: 5/5

Total: **20/20 problemas de seguridad cerrados**.
