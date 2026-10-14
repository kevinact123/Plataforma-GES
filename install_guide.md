# Guía de instalación y despliegue – Plataforma GES

De **desarrollo** a **producción** sobre Windows con **Laragon**.

| Componente | Versión requerida |
| --- | --- |
| Laragon | Full 6.x o superior |
| PHP | **8.4.12** (x64) |
| MySQL | **8.4.3** |
| Laravel / Sanctum | 12.x / 4.x |
| Node.js (solo para compilar assets) | 20 LTS o 22 LTS |

## Índice

1. [Convenciones](#1-convenciones)
2. [Requisitos previos](#2-requisitos-previos)
3. [Instalar Laragon, PHP 8.4.12 y MySQL 8.4.3](#3-instalar-laragon-php-8412-y-mysql-843)
4. [Extensiones de PHP necesarias](#4-extensiones-de-php-necesarias)
5. [Herramientas opcionales: OCR y PDF](#5-herramientas-opcionales-ocr-y-pdf)
6. [Base de datos](#6-base-de-datos)
7. [Obtener el proyecto](#7-obtener-el-proyecto)
8. [Fase de desarrollo](#8-fase-de-desarrollo)
9. [Paso a producción](#9-paso-a-producción)
10. [Servidor web (Apache) y HTTPS](#10-servidor-web-apache-y-https)
11. [Tareas programadas, cola y servicios](#11-tareas-programadas-cola-y-servicios)
12. [Seguridad: checklist](#12-seguridad-checklist)
13. [Respaldos](#13-respaldos)
14. [Verificación posterior al despliegue](#14-verificación-posterior-al-despliegue)
15. [Actualizaciones futuras](#15-actualizaciones-futuras)
16. [Solución de problemas](#16-solución-de-problemas)

---

## 1. Convenciones

- Los comandos se ejecutan en **PowerShell** o en la **Terminal de Laragon** (Menú → Terminal), que ya trae PHP, Composer, Node y Git en el `PATH`.
### 1.1 Cómo distinguir ejemplos de valores reales

En los bloques de código hay tres tipos de valores:

| Tipo | Cómo se ve | Qué hacer |
| --- | --- | --- |
| **Obligatorio reemplazar** | Entre `<` y `>` en mayúsculas, p. ej. `<DOMINIO>` | **Nunca copiar tal cual.** Reemplazar (incluidos los `<` `>`) por tu valor real. |
| **Valor sugerido (ejemplo)** | Los de la tabla 1.2 | Puedes usarlo igual o cambiarlo. Si lo cambias, cámbialo **en todos** los lugares donde aparece. |
| **Valor fijo** | Todo lo demás: versiones (PHP 8.4.12, MySQL 8.4.3), nombres de variables `.env`, comandos `php artisan`, nombres de archivos del proyecto | Usar exactamente como está. |

### 1.2 Valores sugeridos (ejemplos ajustables)

| Valor | Ejemplo usado en la guía | Cambiar si… |
| --- | --- | --- |
| Carpeta del proyecto | `C:\laragon\www\plataforma-ges` | instalas en otra ruta |
| Carpeta de PHP | `C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64` | tu Laragon está en otra ruta |
| Carpeta de MySQL | `C:\laragon\bin\mysql\mysql-8.4.3-winx64` | tu Laragon está en otra ruta |
| Nombre de la base de datos | `plataforma_ges` | tu base real tiene otro nombre |
| Usuario de MySQL de la app | `ges_app` | prefieres otro nombre |
| Host local de desarrollo | `plataforma-ges.test` | cambias el nombre de la carpeta |
| Carpeta de respaldos | `C:\respaldos` | usas otro disco o ruta |
| Usuario administrador inicial | `admin` | quieres otro usuario |

### 1.3 Valores que SIEMPRE debes reemplazar

| Marcador | Qué es |
| --- | --- |
| `<DOMINIO>` | Dominio público de producción (p. ej. el que te asigne TI) |
| `<HOST_SMTP>`, `<PUERTO_SMTP>` | Servidor y puerto de correo (los entrega tu proveedor/TI) |
| `<CORREO_REMITENTE>` | Dirección desde la que se envían los códigos OTP |
| `<RUTA_RESPALDO_BASE>` | Ruta del archivo `.sql` con el esquema base de la plataforma |
| `<ARCHIVO_CERTIFICADO>`, `<ARCHIVO_CLAVE_PRIVADA>` | Certificado SSL y su clave (nombres de tus archivos) |

### 1.4 Contraseñas (textos de ejemplo, NO reales)

Las contraseñas se dejan con un texto de ejemplo. **Debes sustituirlas por claves propias, largas y únicas** (la misma clave debe repetirse donde se usa).

| Texto en la guía | Dónde se usa | Qué es |
| --- | --- | --- |
| `CAMBIAR_CLAVE_ROOT` | `ALTER USER 'root'...` | Contraseña de `root` de MySQL |
| `CAMBIAR_CLAVE_SEGURA` | `CREATE USER 'ges_app'...`, `DB_PASSWORD`, respaldo con `mysqldump` | Contraseña del usuario `ges_app`; debe ser **idéntica** en los tres lugares |
| `CLAVE_LARGA_Y_UNICA` | `ADMIN_PASSWORD` (producción) | Contraseña inicial del administrador de la plataforma |
| `usuario_smtp` / `clave_smtp` | `MAIL_USERNAME` / `MAIL_PASSWORD` | Credenciales del servidor de correo |
| `12345678` | `ADMIN_PASSWORD` (desarrollo) | Clave insegura solo para desarrollo local |

> **Ninguna** contraseña de esta guía es real.

> **Importante:** el proyecto **no usa** las tablas `sessions`, `jobs` ni `cache` en la base de datos (no existen migraciones para ellas). Por eso en esta guía se usan `SESSION_DRIVER=file`, `CACHE_STORE=file` y `QUEUE_CONNECTION=sync`.

---

## 2. Requisitos previos

Antes de empezar, ten a mano:

- [ ] Acceso de administrador a Windows.
- [ ] Instalador de Laragon Full.
- [ ] ZIP de PHP 8.4.12 y ZIP de MySQL 8.4.3.
- [ ] **Respaldo/esquema SQL base** de la plataforma GES (ver sección 6).
- [ ] Acceso al repositorio `https://github.com/kevinact123/Plataforma-GES.git`.
- [ ] Datos del servidor SMTP (necesario para los códigos OTP de inicio de sesión).
- [ ] (Producción) Dominio apuntando al servidor y certificado SSL.

---

## 3. Instalar Laragon, PHP 8.4.12 y MySQL 8.4.3

### 3.1 Laragon

1. Descarga **Laragon Full** desde <https://laragon.org/download> e instálalo (por defecto en `C:\laragon`).
2. Ábrelo y confirma que arranca (**Iniciar todo**).

### 3.2 PHP 8.4.12

1. Descarga desde <https://windows.php.net/downloads/releases/archives/> el ZIP `php-8.4.12-nts-Win32-vs17-x64.zip` (Non Thread Safe, x64).
2. Descomprímelo en `C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64`.
3. Dentro de esa carpeta copia el archivo de configuración:

   ```powershell
   cd C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64
   copy php.ini-development php.ini     # desarrollo
   # copy php.ini-production php.ini    # producción
   ```
4. En Laragon: **Menú → PHP → Versión → php-8.4.12**. Reinicia Apache.

> Si usas Apache con `mod_php`, Laragon necesita la variante *Thread Safe*. Con PHP-FPM/CGI o la CLI sirve la NTS. Usa la que Laragon te muestre como compatible.

### 3.3 MySQL 8.4.3

1. Descarga desde <https://downloads.mysql.com/archives/community/> el ZIP *Windows (x86, 64-bit)* de la versión **8.4.3**.
2. Descomprímelo en `C:\laragon\bin\mysql\mysql-8.4.3-winx64`.
3. En Laragon: **Menú → MySQL → Versión → mysql-8.4.3**.
4. Si el servicio no arranca por falta del directorio de datos (primera vez):

   ```powershell
   cd C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin
   .\mysqld.exe --initialize-insecure --console
   ```
   Esto deja a `root` **sin contraseña** (se la asignas en la sección 6).

### 3.4 Verificar versiones

```powershell
php -v           # PHP 8.4.12
mysql --version  # mysql  Ver 8.4.3
composer -V      # Composer 2.x
node -v          # v20.x o v22.x
npm -v
git --version
```

---

## 4. Extensiones de PHP necesarias

Habilítalas en `php.ini` (Menú Laragon → PHP → Extensiones, o edita el archivo) quitando el `;` inicial:

| Extensión | Para qué se usa |
| --- | --- |
| `pdo_mysql`, `mysqli` | Conexión a MySQL |
| `mbstring` | Procesamiento de texto (nombres, RUT, búsqueda) |
| `openssl` | Cifrado, sesiones y tokens |
| `fileinfo` | Detección del tipo de archivo al subir documentos |
| `zip` | Lectura de `.docx` / `.xlsx` (`ZipArchive`) |
| `dom`, `xml`, `simplexml`, `xmlwriter` | Análisis de documentos Excel/Word |
| `curl` | Composer y envío SMTP/HTTP |
| `ctype`, `tokenizer`, `json` | Requeridas por Laravel |
| `bcmath` | Recomendada por Laravel |
| `intl` | Formato regional (recomendada) |
| `gd` | Imágenes (recomendada) |
| `sodium` | Criptografía (recomendada) |
| `pdo_sqlite`, `sqlite3` | **Solo desarrollo**: las pruebas `php artisan test` usan SQLite en memoria |

Verificar:

```powershell
php -m | Select-String "pdo_mysql|mbstring|openssl|fileinfo|zip|dom|xml|curl|ctype|tokenizer|intl|gd|sodium"
```

Luego reinicia Apache (Menú → Apache → Reiniciar).

---

## 5. Herramientas opcionales: OCR y PDF

Solo son necesarias si quieres que el sistema detecte pacientes en documentos escaneados o PDFs (`DocumentPatientDetectionService`). Sin ellas la plataforma funciona, pero esa detección no.

| Herramienta | Descarga | Variable en `.env` |
| --- | --- | --- |
| Tesseract OCR 5.x + idioma `spa` | <https://github.com/UB-Mannheim/tesseract/wiki> | `OCR_TESSERACT_PATH` |
| Poppler para Windows (`pdftotext`, `pdftoppm`) | <https://github.com/oschwartz10612/poppler-windows/releases> | `OCR_PDFTOTEXT_PATH`, `OCR_PDFTOPPM_PATH` |

Durante la instalación de Tesseract marca el paquete de idioma **Spanish**. Comprueba:

```powershell
& "C:\Program Files\Tesseract-OCR\tesseract.exe" --list-langs   # debe listar "spa"
& "C:\poppler\Library\bin\pdftotext.exe" -v
```

---

## 6. Base de datos

> ⚠️ **Las migraciones del repositorio solo amplían una base GES ya existente.** No crean las tablas base (`usuarios`, `roles`, pacientes, registros GES, patologías, etc.). Debes **restaurar primero el esquema/respaldo base** y luego migrar. Una de las migraciones (`normalize_roles`) consolida roles y **no es reversible**: respalda antes.

### 6.1 Crear base y usuario

```powershell
mysql -u root          # en instalación nueva de Laragon no pide clave
```

```sql
CREATE DATABASE `plataforma_ges` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Usuario exclusivo de la aplicación (no usar root en producción)
CREATE USER 'ges_app'@'localhost' IDENTIFIED BY 'CAMBIAR_CLAVE_SEGURA';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
  ON `plataforma_ges`.* TO 'ges_app'@'localhost';

-- Asignar clave a root
ALTER USER 'root'@'localhost' IDENTIFIED BY 'CAMBIAR_CLAVE_ROOT';
FLUSH PRIVILEGES;
EXIT;
```

### 6.2 Restaurar el esquema/respaldo base

```powershell
mysql -u root -p plataforma_ges < <RUTA_RESPALDO_BASE>
```

### 6.3 Notas de MySQL 8.4

- `mysql_native_password` viene **deshabilitado** en 8.4; se usa `caching_sha2_password` (PHP 8.4 lo soporta sin cambios).
- Si un cliente externo (HeidiSQL, Workbench antiguo) no puede conectar, actualízalo en vez de volver al plugin antiguo.
- En `C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini` (o la plantilla de Laragon) revisa:

  ```ini
  [mysqld]
  bind-address = 127.0.0.1
  character-set-server = utf8mb4
  collation-server = utf8mb4_unicode_ci
  ```

> El `.env.example` trae `DB_DATABASE="plataforma ges"` (con espacio). Usa el **nombre real** de tu base. Esta guía usa `plataforma_ges` solo como **ejemplo** (ver 1.2).

---

## 7. Obtener el proyecto

```powershell
cd C:\laragon\www
git clone https://github.com/kevinact123/Plataforma-GES.git plataforma-ges
cd plataforma-ges
```

El host virtual `http://plataforma-ges.test` se crea solo al reiniciar Laragon (opción *Auto virtual hosts* activa).

---

## 8. Fase de desarrollo

### 8.1 Dependencias y `.env`

```powershell
composer install
npm install
copy .env.example .env
php artisan key:generate
```

### 8.2 `.env` de desarrollo

```dotenv
APP_NAME="Plataforma GES"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://plataforma-ges.test
APP_TIMEZONE=America/Santiago
APP_LOCALE=es

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=plataforma_ges
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log          # los códigos OTP quedan en storage/logs/laravel.log

# Solo desarrollo. 
ADMIN_USERNAME=admin
ADMIN_NAME=Administrador
ADMIN_LASTNAME=GES
ADMIN_PASSWORD=tichsc102
```

### 8.3 Migraciones y datos iniciales

```powershell
php artisan migrate
php artisan db:seed
php artisan db:seed --class=PatologiasGesSeeder
php artisan storage:link
```

- `db:seed` crea los roles (1 Administrador, 2 Supervisor, 3 Digitadora) y el usuario administrador con las variables `ADMIN_*`. Falla si `ADMIN_PASSWORD` está vacío.
- Opcionalmente define `DIGITADORA_USERNAME` y `DIGITADORA_PASSWORD` para crear una digitadora de prueba.

### 8.4 Ejecutar

```powershell
composer run dev
```

Levanta el servidor (`php artisan serve`), la cola, los logs (`pail`) y Vite. Alternativa con Laragon: `npm run dev` y abrir `http://plataforma-ges.test`.

### 8.5 Pruebas

```powershell
php artisan test
```

Usan SQLite en memoria (requiere `pdo_sqlite`). `MariaDbConnectionTest` además valida la conexión configurada.

---

## 9. Paso a producción

### 9.1 Respaldo previo (obligatorio)

```powershell
New-Item -ItemType Directory -Force C:\respaldos | Out-Null
mysqldump -u root -p --single-transaction --routines --triggers plataforma_ges > C:\respaldos\ges_antes_de_desplegar.sql
```

### 9.2 Código y dependencias

```powershell
cd C:\laragon\www\plataforma-ges
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

`npm run build` genera `public\build`. Después puedes eliminar `node_modules` del servidor.

### 9.3 `php.ini` de producción

Usa `php.ini-production` como base y ajusta:

```ini
display_errors = Off
log_errors = On
expose_php = Off
error_reporting = E_ALL & ~E_DEPRECATED
memory_limit = 256M
max_execution_time = 120
upload_max_filesize = 20M     ; el sistema acepta archivos de hasta 20 MB
post_max_size = 25M
date.timezone = America/Santiago

[opcache]
opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0   ; reinicia Apache tras cada despliegue
```

### 9.4 `.env` de producción

> Todo valor entre `<...>` es un **marcador que debes reemplazar** (ver 1.3). Los demás valores (`plataforma_ges`, `ges_app`, `admin`) son **ejemplos ajustables** (ver 1.2).

```dotenv
APP_NAME="Plataforma GES"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<DOMINIO>
APP_TIMEZONE=America/Santiago
APP_LOCALE=es
APP_FALLBACK_LOCALE=es

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=plataforma_ges
DB_USERNAME=ges_app
DB_PASSWORD=CAMBIAR_CLAVE_SEGURA

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

# Correo real: OBLIGATORIO, envía los códigos OTP de acceso
MAIL_MAILER=smtp
MAIL_HOST=<HOST_SMTP>
MAIL_PORT=<PUERTO_SMTP>
MAIL_USERNAME=usuario_smtp
MAIL_PASSWORD=clave_smtp
MAIL_SCHEME=null
MAIL_FROM_ADDRESS="<CORREO_REMITENTE>"
MAIL_FROM_NAME="${APP_NAME}"

# Credenciales iniciales (usar claves fuertes; se usan solo al sembrar)
ADMIN_USERNAME=admin
ADMIN_NAME=Administrador
ADMIN_LASTNAME=GES
ADMIN_PASSWORD=CLAVE_LARGA_Y_UNICA

# OCR (opcional; rutas reales del servidor)
OCR_TESSERACT_PATH="C:\Program Files\Tesseract-OCR\tesseract.exe"
OCR_PDFTOTEXT_PATH="C:\poppler\Library\bin\pdftotext.exe"
OCR_PDFTOPPM_PATH="C:\poppler\Library\bin\pdftoppm.exe"
OCR_LANG=spa

VITE_APP_NAME="${APP_NAME}"
```

> - `SESSION_SECURE_COOKIE=true` exige HTTPS; sin certificado no podrás iniciar sesión.
> - `MAIL_MAILER=log` **no** debe usarse en producción: los usuarios no recibirían el OTP.
> - Si cambias `APP_KEY` después de iniciar, se invalidan sesiones y datos cifrados.

### 9.5 Clave de la aplicación

Solo la **primera vez**. Respáldala en un lugar seguro y no la regeneres:

```powershell
php artisan key:generate --force
```

### 9.6 Migraciones, datos y almacenamiento

```powershell
php artisan migrate --force
php artisan db:seed --force
php artisan db:seed --class=PatologiasGesSeeder --force
php artisan storage:link
```

### 9.7 Cachés de optimización

```powershell
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Tras cualquier cambio en `.env`, repite `php artisan config:cache`.

### 9.8 Permisos de carpetas

La cuenta con la que corre Apache/PHP debe poder **escribir** en `storage` y `bootstrap\cache`. Los documentos subidos se guardan en `storage\app\private`, y los logs en `storage\logs`.

```powershell
icacls "C:\laragon\www\plataforma-ges\storage" /grant "Users:(OI)(CI)M" /T
icacls "C:\laragon\www\plataforma-ges\bootstrap\cache" /grant "Users:(OI)(CI)M" /T
```

Sustituye `Users` por la cuenta real del servicio. Restringe `.env` para que solo la lean administradores y esa cuenta.

---

## 10. Servidor web (Apache) y HTTPS

El *DocumentRoot* debe ser la carpeta **`public`**, nunca la raíz del proyecto.

### 10.1 Host virtual

Archivo: `C:\laragon\etc\apache2\sites-enabled\ges.conf`

```apache
<VirtualHost *:80>
    ServerName <DOMINIO>
    Redirect permanent / https://<DOMINIO>/
</VirtualHost>

<VirtualHost *:443>
    ServerName <DOMINIO>
    DocumentRoot "C:/laragon/www/plataforma-ges/public"

    SSLEngine on
    SSLCertificateFile    "C:/laragon/etc/ssl/<ARCHIVO_CERTIFICADO>"
    SSLCertificateKeyFile "C:/laragon/etc/ssl/<ARCHIVO_CLAVE_PRIVADA>"

    <Directory "C:/laragon/www/plataforma-ges/public">
        AllowOverride All
        Require all granted
    </Directory>

    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Strict-Transport-Security "max-age=31536000"
</VirtualHost>
```

### 10.2 Pasos

1. Confirma que `mod_rewrite`, `mod_ssl` y `mod_headers` estén cargados (Laragon los trae activos).
2. Coloca el certificado y la clave en `C:\laragon\etc\ssl\` (de la institución o Let's Encrypt con `win-acme`).
3. Reinicia Apache: Menú → Apache → Reiniciar.
4. Abre el puerto **443** (y 80 para la redirección) en el Firewall de Windows:

   ```powershell
   New-NetFirewallRule -DisplayName "GES HTTP"  -Direction Inbound -Protocol TCP -LocalPort 80  -Action Allow
   New-NetFirewallRule -DisplayName "GES HTTPS" -Direction Inbound -Protocol TCP -LocalPort 443 -Action Allow
   ```
5. Verifica: `curl -I https://<DOMINIO>/login`.

---

## 11. Tareas programadas, cola y servicios

### 11.1 Scheduler (obligatorio)

La asignación automática de registros (`php artisan asignaciones:automaticas`) está programada cada 5 minutos en `routes/console.php`. Para que corra, Windows debe ejecutar `schedule:run` **cada minuto**:

```powershell
schtasks /Create /TN "GES Scheduler" /SC MINUTE /MO 1 /RU SYSTEM /TR "C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64\php.exe C:\laragon\www\plataforma-ges\artisan schedule:run"
```

Verifica con `php artisan schedule:list` y revisando el Programador de tareas.

### 11.2 Cola

Con `QUEUE_CONNECTION=sync` (valor recomendado) **no se necesita worker**. Solo si más adelante cambias a otro driver, instala un worker (por ejemplo con [NSSM](https://nssm.cc)) y ejecuta `php artisan queue:restart` en cada despliegue.

### 11.3 Servicios de Windows y arranque automático

Para que la plataforma sobreviva a reinicios del servidor:

1. Laragon → Preferencias → General: activar *Run Laragon when Windows starts* y *Start all automatically*.
2. Recomendado en producción: instalar Apache y MySQL como servicios (Menú → Herramientas → Servicios) para que arranquen sin iniciar sesión.

---

## 12. Seguridad: checklist

- [ ] `APP_ENV=production` y `APP_DEBUG=false`.
- [ ] `APP_KEY` generada y respaldada.
- [ ] `root` de MySQL con contraseña; la app usa `ges_app`.
- [ ] MySQL solo en `127.0.0.1` y puerto 3306 **cerrado** en el firewall.
- [ ] Contraseña del administrador cambiada tras el primer ingreso; eliminar `ADMIN_PASSWORD` y `DIGITADORA_PASSWORD` del `.env` luego de sembrar.
- [ ] HTTPS activo, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`.
- [ ] SMTP real funcionando (OTP).
- [ ] DocumentRoot = `public`; `.env`, `storage`, `vendor` y `.git` inaccesibles desde la web.
- [ ] Permisos de `.env` restringidos.
- [ ] Respaldos automáticos probados (sección 13).
- [ ] Windows y Laragon actualizados; solo puertos 80/443 expuestos.

---

## 13. Respaldos

Respalda **la base de datos** y **los documentos subidos** (`storage\app\private`).

```powershell
# Base de datos (tarea diaria 02:00)
schtasks /Create /TN "GES Backup BD" /SC DAILY /ST 02:00 /RU SYSTEM /TR "cmd /c C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe -u ges_app -pCAMBIAR_CLAVE_SEGURA --single-transaction --routines --triggers plataforma_ges > C:\respaldos\ges_diario.sql"

# Documentos (tarea diaria 02:30)
schtasks /Create /TN "GES Backup Docs" /SC DAILY /ST 02:30 /RU SYSTEM /TR "robocopy C:\laragon\www\plataforma-ges\storage\app\private C:\respaldos\documentos /MIR"
```

Mejora recomendada: nombre con fecha, rotación y copia fuera del servidor. **Prueba restaurar** un respaldo periódicamente:

```powershell
mysql -u root -p plataforma_ges < C:\respaldos\ges_diario.sql
```

---

## 14. Verificación posterior al despliegue

```powershell
php artisan about
php artisan migrate:status
php artisan schedule:list
php artisan route:list --except-vendor
curl -I https://<DOMINIO>/login
Get-ChildItem storage\logs | Sort-Object LastWriteTime | Select-Object -Last 1 | Get-Content -Tail 50
```

Prueba manual:

1. Abrir `https://<DOMINIO>` (debe redirigir a `/login`).
2. Iniciar sesión con el administrador → debe llegar el **código OTP** por correo → validarlo.
3. Abrir el dashboard.
4. Subir un documento (PDF/Excel) y descargarlo.
5. Ejecutar `php artisan asignaciones:automaticas` y revisar que no haya errores.
6. Cambiar la contraseña del administrador.

---

## 15. Actualizaciones futuras

```powershell
cd C:\laragon\www\plataforma-ges
php artisan down
mysqldump -u ges_app -p --single-transaction --routines --triggers plataforma_ges > C:\respaldos\ges_pre_update.sql
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
# Reiniciar Apache (opcache con validate_timestamps=0)
php artisan up
```

**Reversión rápida:** `php artisan down`, restaurar el respaldo SQL, volver al commit anterior (`git checkout <commit>`), repetir `composer install --no-dev` y las cachés, `php artisan up`.

---

## 16. Solución de problemas

| Síntoma | Causa probable | Solución |
| --- | --- | --- |
| `could not find driver` | Falta `pdo_mysql` | Habilitarla en `php.ini` y reiniciar Apache |
| `SQLSTATE[HY000] [1045] Access denied` | Usuario/clave de `.env` incorrectos | Revisar `DB_*`; luego `php artisan config:clear` |
| `SQLSTATE[HY000] [2002]` | MySQL detenido | Iniciar MySQL en Laragon |
| `Base table or view not found` | Falta el esquema base | Restaurar el respaldo base antes de migrar (sección 6) |
| Error 500 sin detalle | Permisos o `.env` incompleto | Ver `storage\logs\laravel.log`; revisar permisos de `storage` |
| `No application encryption key` | Falta `APP_KEY` | `php artisan key:generate` y `config:cache` |
| Página sin estilos/JS | No se compiló o no hay `public\build` | `npm run build` |
| Documentos/imágenes no cargan | Falta enlace | `php artisan storage:link` |
| Cambios de `.env` sin efecto | Config cacheada | `php artisan config:clear` y luego `config:cache` |
| No llegan códigos OTP | SMTP mal o `MAIL_MAILER=log` | Revisar `MAIL_*`; en desarrollo ver `laravel.log` |
| No se puede iniciar sesión en producción | `SESSION_SECURE_COOKIE=true` sin HTTPS | Activar HTTPS o corregir `APP_URL` |
| Subida de archivos falla | Límites de PHP | Subir `upload_max_filesize` y `post_max_size` (máx. del sistema: 20 MB) |
| Rutas devuelven 404 (menos `/`) | `mod_rewrite` / `AllowOverride` | Habilitar `mod_rewrite` y `AllowOverride All` |
| Asignaciones automáticas no corren | Falta la tarea programada | Crear `GES Scheduler` (sección 11.1) |
| OCR no detecta texto | Rutas `OCR_*` o falta idioma `spa` | Revisar rutas y `tesseract --list-langs` |
| MySQL no arranca tras cambiar de versión | Falta directorio de datos | `mysqld --initialize-insecure --console` (sección 3.3) |
| Error por bloqueo de OTP / demasiados intentos | Límite por 5 minutos (5 envíos, 10 verificaciones) | Esperar 5 minutos |
