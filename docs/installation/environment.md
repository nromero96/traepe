# Entorno base — Checkpoint 00A

## Versiones aprobadas

- PHP 8.4.
- Laravel 13.
- Composer 2.x.

En la estación Windows usada para 00A se instaló PHP 8.4 de forma portátil fuera del repositorio en `C:\tools\traepe-php84`. XAMPP conserva su PHP existente y no debe usarse para este proyecto mientras no cumpla PHP 8.4.

## Instalación de dependencias PHP

Ejecutar Composer con un binario PHP 8.4 explícito. Desde `apps/api`:

```powershell
& 'C:\tools\traepe-php84\php.exe' 'C:\tools\composer-latest.phar' install --no-interaction
```

El entorno de desarrollo desactiva `optimize-autoloader` por el costo observado en Windows. CI/producción podrán solicitar optimización explícita cuando se definan sus comandos.

## Configuración

1. Copiar `.env.example` a `.env`.
2. Generar una clave local con `php artisan key:generate` usando PHP 8.4.
3. No versionar `.env`; ya está excluido por `.gitignore`.
4. No colocar secretos reales en `.env.example`.

Durante 00A, antes de PostgreSQL/Redis:

- `DB_CONNECTION=sqlite`;
- `QUEUE_CONNECTION=sync`;
- `CACHE_STORE=file`;
- `BROADCAST_CONNECTION=log`;
- `FILESYSTEM_DISK=local`;
- `MAIL_MAILER=log`;
- sesiones: `file`.

Estas configuraciones son temporales y serán sustituidas progresivamente desde Checkpoint 00B según ADR-003 y el orden aprobado. No anticipar conexiones ni servicios posteriores en 00A.

## Validación 00A

```powershell
php --version
php artisan --version
php artisan test
php artisan serve --host=127.0.0.1 --port=8013
```

La ejecución debe usar PHP 8.4 aunque el ejemplo abrevie el path. La aplicación debe responder HTTP 200 y no requerir servicios de 00B.

## Seguridad

- `APP_DEBUG=false` es el default versionado.
- Las sesiones se cifran.
- `.env.example` contiene marcadores vacíos, no credenciales.
- Ningún proveedor productivo se configura en 00A.
