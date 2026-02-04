# Migración del Módulo de Autenticación

## Resumen
Se migró el módulo de autenticación de `/auth/` a `/ecommerce/admin/auth/` para mantener la estructura integrada del panel admin del ecommerce.

## Cambios Realizados

### 1. Archivos Creados en `/ecommerce/admin/auth/`
- **login.php** - Página de login para el admin (con form action="check.php")
- **check.php** - Verifica credenciales y redirige al admin index
- **logout.php** - Destruye la sesión y redirige a login

### 2. Actualización de Rutas
Los archivos migrados usan rutas correctas:
- `$base_path` se calcula con 4 niveles de `dirname()` para llegar a la raíz
- Carga `config.php` desde `/config.php`
- Accede a logos desde `../../ecommerce/uploads/`
- Las rutas de redirección usan rutas relativas (`../index.php`, `login.php`)

### 3. Cambios en `/ecommerce/admin/includes/header.php`
- **Línea 27**: Cambió de `header("Location: " . $relative_root . "auth/login.php")` a `header("Location: /ecommerce/admin/auth/login.php")`
- **Línea 89**: Cambió de `href="<?= $relative_root ?>cambiar_clave.php"` a `href="<?= $admin_url ?>cambiar_clave.php"`
- **Línea 91**: Cambió de `href="<?= $relative_root ?>auth/logout.php"` a `href="<?= $admin_url ?>auth/logout.php"`

### 4. Backward Compatibility en `/auth/`
Los archivos originales en `/auth/` ahora redirigen a la nueva ubicación:
- `/auth/login.php` → redirige a `/ecommerce/admin/auth/login.php`
- `/auth/check.php` → redirige a `/ecommerce/admin/auth/check.php`
- `/auth/logout.php` → redirige a `/ecommerce/admin/auth/logout.php`

Esto permite que cualquier código legado que referencia el auth antiguo siga funcionando.

## Flujo de Autenticación (Nuevo)

1. **Acceso sin login** → Usuario intenta acceder a `/ecommerce/admin/sueldos/sueldos.php`
2. **Header.php valida** → Detecta que `$_SESSION['user']` no existe
3. **Redirección** → Redirige a `/ecommerce/admin/auth/login.php`
4. **Login** → Usuario ingresa credenciales
5. **Check.php** → Valida credenciales en la BD
6. **Success** → Crea sesión y redirige a `/ecommerce/admin/index.php`
7. **Admin** → Usuario ve el panel admin del ecommerce
8. **Logout** → Link en dropdown redirige a `/ecommerce/admin/auth/logout.php`
9. **Logout completo** → Sesión destruida, redirige a login

## Verificación

✅ Login en: `/ecommerce/admin/auth/login.php`
✅ Después del login: Redirige a `/ecommerce/admin/index.php`
✅ Logout: Redirige a `/ecommerce/admin/auth/login.php`
✅ Links en dropdown del usuario usan `$admin_url` (URLs absolutas)
✅ Backward compatibility: `/auth/login.php` funciona (redirige)

## Archivos Modificados
- `/ecommerce/admin/auth/login.php` ✅ CREADO
- `/ecommerce/admin/auth/check.php` ✅ CREADO
- `/ecommerce/admin/auth/logout.php` ✅ CREADO
- `/ecommerce/admin/includes/header.php` ✅ ACTUALIZADO
- `/auth/login.php` ✅ CONVERTIDO A REDIRECT
- `/auth/check.php` ✅ CONVERTIDO A REDIRECT
- `/auth/logout.php` ✅ CONVERTIDO A REDIRECT
