# ☁️ "La Nube" (fotos) - Panel Web, PWA y App Móvil

Este documento explica qué es "la nube" dentro del sistema, cómo se relaciona con el panel PHP y con la app móvil, y cómo instalar cada parte desde cero.

---

## 🧩 ¿Qué es "la nube" acá?

No es un servicio externo (no hay S3, Google Drive, Firebase ni Dropbox). Es una **galería de fotos con carpetas guardada en el propio servidor**, en `ecommerce/uploads/fotos_nube/`, con tres formas de acceder a lo mismo:

1. **Panel web (PHP)** → `ecommerce/admin/fotos_nube.php`
2. **PWA instalable** → el mismo panel admin, pero "instalado" como app desde el navegador del celu
3. **App móvil nativa "La Nube"** → `mobile-nube/` (Expo / React Native), habla con una API REST propia

Las tres opciones leen y escriben **los mismos archivos y la misma base de datos** — no hay sincronización real porque no hace falta: todas trabajan directo contra el servidor.

```
                     ┌─────────────────────────┐
                     │   Servidor PHP (Apache)  │
                     │  ecommerce/uploads/      │
                     │     fotos_nube/  ← disco │
                     └───────────┬───────────────┘
                                 │
              ┌──────────────────┼───────────────────┐
              │                  │                    │
     fotos_nube.php      ecommerce/api/nube/*    (misma cosa,
     (HTML directo)       (JSON + token Bearer)   sin API)
              │                  │
     ┌────────┴────────┐        │
     │  Navegador       │        │
     │  normal o PWA    │   App móvil "La Nube"
     │  instalada       │   (Expo / React Native)
     └──────────────────┘
```

---

## 🗂️ Cómo funciona cada capa

### 1. Panel web clásico (`ecommerce/admin/fotos_nube.php`)
- Explorador de carpetas dentro del admin: crear carpetas, subir fotos, moverlas, borrarlas, descargar en ZIP, previsualizar.
- Usa los helpers compartidos de `ecommerce/includes/fotos_nube_helpers.php` (validación de rutas, listado recursivo, etc.).
- Acceso con la sesión normal del panel (usuario/contraseña de `usuarios`).

### 2. PWA instalable (commit `f02ccc5` "cambiis fotos en la nube!")
- Se agregó `ecommerce/admin/manifest.json` y `ecommerce/admin/sw.js` (service worker), más íconos en `assets/pwa/`.
- `includes/header.php` linkea el manifest y el `theme-color`; `includes/footer.php` registra el service worker.
- El service worker **solo cachea íconos estáticos** — todo el contenido (datos, fotos) siempre va a la red. No funciona offline, solo da la experiencia de "instalar" el panel como ícono en el celular/PC.
- No requiere nada especial para instalarse: el navegador (Chrome/Edge/Safari) ofrece "Agregar a pantalla de inicio" solo con visitar el panel por HTTPS.

### 3. API REST (`ecommerce/api/nube/*`, commit `66981e8` "subida grande")
Endpoints disponibles:

| Endpoint | Función |
|---|---|
| `login.php` | Login con usuario/contraseña → devuelve token |
| `logout.php` | Invalida el token |
| `listar.php` | Lista archivos/carpetas |
| `carpetas.php` | Lista solo carpetas |
| `carpeta_crear.php` | Crea carpeta |
| `carpeta_eliminar.php` | Borra carpeta |
| `archivo_eliminar.php` | Borra archivo |
| `mover.php` | Mueve archivo/carpeta |
| `subir.php` | Sube un archivo |
| `descargar_zip.php` | Descarga selección como ZIP |

- Autenticación por **token Bearer**, validado en `ecommerce/includes/nube_auth.php` contra la tabla `nube_api_tokens` (creada por `migracion_nube_api_tokens.sql`).
- Los tokens están ligados a los **mismos usuarios** del panel admin — no hay cuentas separadas para la app.
- Reusa las mismas funciones de `fotos_nube_helpers.php` que usa el panel web (por eso el refactor grande del commit `66981e8`: se sacaron esas funciones de adentro de `fotos_nube.php` para poder compartirlas con la API).

### 4. App móvil "La Nube" (`mobile-nube/`)
- Proyecto **Expo / React Native / TypeScript** independiente, con su propio `package.json` (no se compila junto al PHP).
- Pantallas (`mobile-nube/app/`, con `expo-router`):
  - `login.tsx` — pide **URL del servidor** + usuario/contraseña del panel admin
  - `index.tsx` — explorador de carpetas/fotos, subir desde cámara o galería, crear/borrar carpetas, mover, seleccionar y compartir un ZIP
  - `preview.tsx` — vista de una foto
- Cliente API (`mobile-nube/src/api/`) usa `axios` contra `<serverUrl>/ecommerce/api/nube/<endpoint>`, con `Authorization: Bearer <token>`.
- El token y la URL del servidor se guardan en el celular con `expo-secure-store` (`mobile-nube/src/state/auth.tsx`).
- **No hay build de distribución committeado** (no hay config de EAS): hoy se corre en modo desarrollo con Expo Go, o habría que generar un build propio.

---

## ⚠️ Puntos a tener en cuenta

- `config.php` y `ecommerce/config.php` tienen las credenciales de la base de datos **en texto plano** dentro del repo. Si en algún momento se comparte este repositorio (por ejemplo con quien arme el build de la app), hay que rotar esas credenciales o sacarlas del control de versiones.
- El código usa `str_starts_with()` (PHP ≥ 8.0), aunque `composer.json` todavía declara `"php": ">=5.5.0"`. El servidor real necesita **PHP 8.0 o superior**, ese `composer.json` está desactualizado.
- La API `ecommerce/api/nube/*` necesita ser accesible por **HTTPS público** para que la app móvil pueda usarla fuera de la red local.

---

## 🚀 Instalación desde cero

### A) Backend PHP (servidor donde vive todo el sistema)

1. **Requisitos del servidor:**
   - Apache con `mod_rewrite`, `mod_deflate`, `mod_expires`
   - PHP 8.0+ con extensiones: `pdo_mysql`, `zip`, `mbstring`, `gd`/`fileinfo`, `openssl`
   - MySQL/MariaDB

2. **Base de datos:**
   - Crear una base MySQL y cargar los datos en `config.php` (raíz) y `ecommerce/config.php`.

3. **Dependencias PHP:**
   ```
   composer install
   ```
   (trae `phpmailer/phpmailer`)

4. **Tablas:** no hay un `schema.sql` único, se arma con scripts que se abren una vez por navegador:
   - `setup_ecommerce.php`, `setup_gastos.php`, `setup_asistencias.php`, `setup_cheques.php`, `setup_pagos.php`, `setup_roles.php`, `setup_sueldos.php`, `setup_cuentas_cli.php`
   - Y los `ecommerce/setup_*.php` propios del e-commerce

5. **Específico de "la nube":** correr manualmente contra la base (por phpMyAdmin o cliente MySQL):
   ```sql
   -- migracion_nube_api_tokens.sql
   ```
   Esto crea la tabla `nube_api_tokens`, necesaria para que la app móvil pueda loguearse.

6. **Permisos de carpetas** (el usuario del proceso PHP necesita escritura):
   ```
   uploads/gastos
   uploads/atributos
   uploads/facturas_pedidos
   ecommerce/uploads/fotos_nube   (se autocrea, pero necesita permiso de escritura en ecommerce/uploads/)
   ```

7. Publicar el `.htaccess` tal cual viene (ya trae reglas de seguridad).

8. Crear al menos un usuario admin en la tabla `usuarios` (revisar `fix_admin_rol.php` / `asignar_admin.php` en la raíz si hace falta un ajuste puntual de rol).

9. **Verificar que "la nube" funciona:**
   - Entrar al panel → `ecommerce/admin/fotos_nube.php` → crear una carpeta de prueba y subir una foto.
   - Confirmar en el navegador que aparece el prompt de "Instalar app" (PWA) al visitar el admin por HTTPS.

### B) App móvil (`mobile-nube/`)

1. Requiere **Node.js** + npm, y el CLI de Expo.
2. Instalar dependencias:
   ```
   cd mobile-nube
   npm install
   ```
3. Correr en modo desarrollo:
   ```
   npm run start
   ```
   (o `npm run android` / `npm run ios` / `npm run web`), y abrir con **Expo Go** en el celular, o un emulador.
4. Para distribuir la app de verdad (no solo modo dev) haría falta configurar **EAS Build** — hoy no está en el repo, es el paso pendiente si se quiere generar un `.apk`/`.ipa` instalable sin Expo Go.
5. **En la app, al loguearse:**
   - URL del servidor: `https://tucuroller.com.ar` (o el dominio real)
   - Usuario/contraseña: los mismos del panel admin
6. Esto solo funciona si el paso **A.5** (migración `migracion_nube_api_tokens.sql`) ya se ejecutó en el servidor.

---

## 📋 Resumen: ¿qué instalar según el caso?

| Quiero... | Necesito instalar |
|---|---|
| Usar el explorador de fotos desde la compu | Solo el backend PHP (parte A) |
| "Instalarlo" como ícono en el celu, usando el navegador | Solo el backend PHP (parte A) — ya viene con PWA |
| Usar la app nativa para subir fotos desde la cámara del celu | Backend PHP (parte A completa, incluyendo la migración de tokens) + app móvil (parte B) |
