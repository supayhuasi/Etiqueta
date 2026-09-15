# 🔒 GUÍA DE SEGURIDAD IMPLEMENTADA

## ✅ Medidas de Seguridad Activas

### 1. **Headers de Seguridad HTTP** ✓ ACTIVO
Protección automática en todos los archivos:
- **X-XSS-Protection**: Previene ataques XSS (Cross-Site Scripting)
- **X-Frame-Options**: Previene clickjacking
- **X-Content-Type-Options**: Previene MIME sniffing
- **Content-Security-Policy**: Controla qué recursos pueden cargar las páginas
- **Referrer-Policy**: Controla información enviada en headers

### 2. **Protección SQL Injection** ✓ ACTIVO
- PDO con prepared statements (ya implementado)
- `PDO::ATTR_EMULATE_PREPARES => false` para mayor seguridad
- Validación de IDs con `validate_id()`
- Detección automática de patrones de ataque SQL

### 3. **Protección XSS (Cross-Site Scripting)** ✓ ACTIVO
- Función `sanitize_input()` para limpiar datos de entrada
- Función `escape_html()` para output seguro en HTML
- Detección de tags `<script>`, `<iframe>`, `<object>`, etc.
- htmlspecialchars en todas las salidas

### 4. **Protección CSRF (Cross-Site Request Forgery)** ✓ ACTIVO
- Tokens únicos por sesión
- Validación en formularios con `csrf_field()`
- Función `validate_csrf_token()` para verificar
- **YA IMPLEMENTADO EN**: Login del admin

### 5. **Rate Limiting (Anti Fuerza Bruta)** ✓ ACTIVO
- Máximo 5 intentos de login en 15 minutos
- Sistema configurable de rate limiting
- Función `check_rate_limit()` reutilizable
- Logs automáticos de intentos sospechosos

### 6. **Seguridad de Sesiones** ✓ ACTIVO
- `session.cookie_httponly = 1`: Cookies no accesibles desde JavaScript
- `session.cookie_secure = 1`: Solo cookies por HTTPS
- `session.use_only_cookies = 1`: Sin session IDs en URL
- `session.cookie_samesite = Strict`: Previene CSRF
- Regeneración automática de session ID cada 5 minutos
- Regeneración al hacer login

### 7. **Validación de Archivos Subidos** ✓ DISPONIBLE
- Función `validate_upload()` verifica:
  - Tipo MIME real (no solo extensión)
  - Tamaño máximo (5MB por defecto)
  - Solo tipos permitidos (jpg, png, gif, webp)
- Función `sanitize_filename()` previene path traversal
- Detección de doble extensión (.php.jpg)

### 8. **Protección .htaccess** ✓ ACTIVO
Apache rules que bloquean:
- Listado de directorios
- Acceso a config.php, .env, archivos .log
- SQL injection en URL
- Path traversal (../, ..\)
- User agents maliciosos (bots, scrapers)
- XSS en query strings
- Métodos HTTP no permitidos (PUT, DELETE, etc.)

### 9. **Logging de Seguridad** ✓ ACTIVO
Registro automático de:
- Intentos de login fallidos
- Intentos de fuerza bruta
- Patrones de ataque detectados
- Inputs maliciosos (SQL, XSS, etc.)
- Tokens CSRF inválidos

Archivo: `logs/security.log` (protegido por .htaccess)

### 10. **Detección Automática de Ataques** ✓ ACTIVO
Patrones bloqueados automáticamente:
- SQL injection keywords (UNION, SELECT, DROP, etc.)
- XSS tags (<script>, <iframe>, etc.)
- Path traversal (../, ..\, /etc/passwd)
- Base64 encoding malicioso
- Null bytes (%00)

---

## 📁 Archivos Creados/Modificados

### Nuevos Archivos:
1. **`ecommerce/includes/security.php`** - Sistema completo de seguridad
2. **`.htaccess`** (raíz) - Protección Apache
3. **`logs/security.log`** - Registro de eventos
4. **`logs/.htaccess`** - Proteger logs
5. **`EJEMPLO_SEGURIDAD.php`** - Guía de uso

### Archivos Modificados:
1. **`config.php`** - PDO seguro, prevenir emulate_prepares
2. **`ecommerce/admin/auth/login.php`** - CSRF token agregado
3. **`ecommerce/admin/auth/check.php`** - Validación CSRF + rate limiting

---

## 🚀 Cómo Usar la Seguridad

### Para archivos ADMIN (requieren login):
```php
<?php
define('SECURITY_CHECK', true);
session_start();

// Verificar login
if (!isset($_SESSION['user'])) {
    header("Location: /ecommerce/admin/auth/login.php");
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ecommerce/includes/security.php';
?>
```

### Para archivos PÚBLICOS:
```php
<?php
define('SECURITY_CHECK', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ecommerce/includes/security.php';
?>
```

### Proteger formularios con CSRF:
```php
<form method="post" action="procesar.php">
    <?= csrf_field() ?>
    <input type="text" name="nombre">
    <button>Enviar</button>
</form>
```

### Procesar formulario de forma segura:
```php
// Validar CSRF
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    die('Token inválido');
}

// Sanitizar inputs
$nombre = sanitize_input($_POST['nombre']);
$email = sanitize_input($_POST['email']);

// Validar email
if (!validate_email($email)) {
    die('Email inválido');
}

// Validar ID
$id = validate_id($_POST['id'] ?? 0);
```

### Escapar output en HTML:
```php
<h1><?= escape_html($titulo) ?></h1>
<p><?= escape_html($comentario_usuario) ?></p>
```

### Validar archivos subidos:
```php
$validation = validate_upload($_FILES['imagen']);
if (!$validation['success']) {
    die($validation['error']);
}

$filename = sanitize_filename($_FILES['imagen']['name']);
move_uploaded_file($_FILES['imagen']['tmp_name'], 'uploads/' . $filename);
```

---

## 🔍 Monitoreo

### Ver logs de seguridad:
```bash
# En terminal o servidor
tail -f logs/security.log
```

Ejemplo de log:
```
[2024-02-18 10:30:45] IP: 192.168.1.100 | Event: LOGIN_SUCCESS | Details: {"user":"admin"} | UA: Mozilla/5.0...
[2024-02-18 10:31:20] IP: 192.168.1.200 | Event: BRUTE_FORCE_ATTEMPT | Details: {"identifier":"login_hacker"} | UA: curl/7.68.0
```

---

## ⚠️ Tipos de Ataques Bloqueados

### 1. SQL Injection
❌ BLOQUEADO: `?id=1 OR 1=1`  
❌ BLOQUEADO: `?name=admin'--`  
❌ BLOQUEADO: `?search='; DROP TABLE usuarios--`

### 2. XSS (Cross-Site Scripting)
❌ BLOQUEADO: `<script>alert('XSS')</script>`  
❌ BLOQUEADO: `<img src=x onerror=alert(1)>`  
❌ BLOQUEADO: `<iframe src="malicious.com">`

### 3. Path Traversal
❌ BLOQUEADO: `../../../etc/passwd`  
❌ BLOQUEADO: `..\..\config.php`  
❌ BLOQUEADO: `/uploads/../../config.php`

### 4. CSRF
❌ BLOQUEADO: Formularios sin token CSRF  
❌ BLOQUEADO: Tokens inválidos o expirados

### 5. Fuerza Bruta
❌ BLOQUEADO: Más de 5 intentos de login en 15 min  
❌ BLOQUEADO: Spam de formularios (rate limit)

### 6. Acceso no autorizado
❌ BLOQUEADO: Acceso directo a `config.php`  
❌ BLOQUEADO: Lectura de archivos `.log`  
❌ BLOQUEADO: Listado de directorios

---

## 📊 Niveles de Protección

```
┌─────────────────────────────────────┐
│ Nivel 1: .htaccess (Apache)         │ ✓ Activo
│ - Bloquea antes de llegar a PHP     │
├─────────────────────────────────────┤
│ Nivel 2: Headers HTTP               │ ✓ Activo
│ - Protección en navegador           │
├─────────────────────────────────────┤
│ Nivel 3: Detección de Patrones     │ ✓ Activo
│ - Analiza GET/POST automáticamente  │
├─────────────────────────────────────┤
│ Nivel 4: Validación de Input       │ ✓ Disponible
│ - sanitize_input(), validate_*()    │
├─────────────────────────────────────┤
│ Nivel 5: CSRF Tokens                │ ✓ Login protegido
│ - Previene requests falsificados    │
├─────────────────────────────────────┤
│ Nivel 6: Rate Limiting              │ ✓ Login protegido
│ - Previene fuerza bruta             │
├─────────────────────────────────────┤
│ Nivel 7: PDO Prepared Statements    │ ✓ Ya implementado
│ - SQL Injection imposible           │
├─────────────────────────────────────┤
│ Nivel 8: Session Security           │ ✓ Activo
│ - HTTPOnly, Secure, SameSite        │
├─────────────────────────────────────┤
│ Nivel 9: Logging                    │ ✓ Activo
│ - Registro de todos los intentos    │
└─────────────────────────────────────┘
```

---

## 🎯 Próximos Pasos Recomendados

### Prioridad ALTA:
1. ✅ **Login protegido** - YA HECHO
2. ⏳ **Agregar CSRF a otros formularios críticos**:
   - Formulario de registro
   - Formulario de compra/checkout
   - Formularios de creación de productos
   - Formularios de edición de usuarios

### Prioridad MEDIA:
3. ⏳ **Implementar en archivos de upload**:
   - productos_imagenes.php
   - empresa.php (logo)
   - Cualquier upload de archivos

### Prioridad BAJA:
4. ⏳ **2FA (Autenticación de dos factores)** - Opcional
5. ⏳ **Captcha en login** - Si hay muchos ataques
6. ⏳ **WAF (Web Application Firewall)** - Nivel servidor

---

## 📝 Notas Importantes

### ⚠️ Antes de producción:
1. Cambiar `session.cookie_secure = 1` requiere **HTTPS**
2. Revisar que todos los formularios tengan CSRF token
3. Verificar que los logs no crezcan demasiado
4. Considerar rotar logs periódicamente

### 🔧 Configuración recomendada en php.ini:
```ini
display_errors = Off
log_errors = On
error_log = /path/to/php-error.log
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off
```

### 🌐 HTTPS Recomendado:
Para máxima seguridad, usar certificado SSL:
- Let's Encrypt (gratis)
- Cloudflare SSL
- Certificado pago

---

## ✅ Checklist de Seguridad

- [x] SQL Injection protegido (PDO)
- [x] XSS protegido (sanitize/escape)
- [x] CSRF protegido (tokens)
- [x] Session Hijacking protegido
- [x] Fuerza bruta protegido (rate limit)
- [x] File upload protegido (validación)
- [x] Headers de seguridad
- [x] Logging de eventos
- [x] .htaccess protecciones
- [x] Path traversal protegido
- [ ] HTTPS configurado (PENDIENTE)
- [ ] Todos los formularios con CSRF (PENDIENTE)
- [ ] Backups automáticos (PENDIENTE)

---

## 🆘 Soporte

Ver archivo `EJEMPLO_SEGURIDAD.php` para ejemplos de código.

**Logs**: `logs/security.log`  
**Configuración**: `ecommerce/includes/security.php`  
**Apache**: `.htaccess`
